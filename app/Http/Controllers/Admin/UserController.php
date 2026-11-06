<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAudit;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $acl = $request->query('acl');
        $status = $request->query('status');

        $sort = (string) $request->query('sort', 'name');
        $dir = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        // Identity and payroll live in the PIS database; helpdesk attributes live
        // in the its database. The two cannot be joined in one query, so the
        // profile rows are fetched separately and merged into the page.
        $query = DB::connection('payroll')
            ->table('user as u')
            ->join('payroll as p', 'u.id', '=', 'p.id')
            ->select([
                'u.id',
                'u.username',
                'p.name',
                'p.mname',
                'p.lname',
                'p.department',
                'p.status',
            ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('u.username', 'like', '%'.$search.'%')
                    ->orWhere('p.name', 'like', '%'.$search.'%')
                    ->orWhere('p.lname', 'like', '%'.$search.'%');
            });
        }

        if ($acl !== null && $acl !== '') {
            $ids = UserProfile::withTrashed()
                ->where('acl_id', (int) $acl)
                ->pluck('user_id')
                ->all();

            if ($ids === []) {
                return response()->json([
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'total' => 0,
                    'per_page' => 40,
                ]);
            }

            $query->whereIn('u.id', $ids);
        }

        if ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('p.status', (string) $status);
        }

        $sortMap = [
            'id' => 'u.id',
            'username' => 'u.username',
            'name' => 'p.lname',
            'department' => 'p.department',
            'status' => 'p.status',
        ];

        $sortColumn = $sortMap[$sort] ?? 'p.lname';

        $perPage = min(100, max(1, (int) $request->query('per_page', 40)));

        $users = $query->orderBy($sortColumn, $dir)->paginate($perPage);

        $profiles = UserProfile::withTrashed()
            ->whereIn('user_id', $users->pluck('id')->all())
            ->get()
            ->keyBy('user_id');

        $rows = array_map(function (object $user) use ($profiles): array {
            $id = (int) $user->id;
            $profile = $profiles->get($id);

            return [
                'id' => $id,
                'username' => $user->username,
                'name' => $user->name,
                'mname' => $user->mname,
                'lname' => $user->lname,
                'department' => $user->department,
                'status' => $user->status,
                'acl_id' => $profile->acl_id ?? null,
                'group_id' => $profile->group_id ?? null,
                'fpc' => $profile->fpc ?? null,
                'login_attempts' => $profile->login_attempts ?? null,
                'last_login_dt' => $profile->last_login_dt ?? null,
                'supervisor_id' => $profile->supervisor_id ?? null,
                'deleted_at' => $profile->deleted_at ?? null,
            ];
        }, $users->items());

        return response()->json([
            'data' => $rows,
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(),
        ]);
    }

    public function updateAccess(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'acl_id' => ['required', 'integer', 'in:1,3,9'],
        ]);

        $profile = UserProfile::firstOrNew(['user_id' => $id]);
        $beforeAcl = $profile->exists ? $profile->acl_id : null;
        $profile->acl_id = (int) $validated['acl_id'];
        $profile->save();

        AdminAudit::create([
            'actor_user_id' => $request->user()->id,
            'action' => 'update_access',
            'entity' => 'user',
            'entity_id' => $id,
            'before' => json_encode(['acl_id' => $beforeAcl]),
            'after' => json_encode(['acl_id' => $validated['acl_id']]),
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => 'Access updated.']);
    }

    public function updateSupervisor(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'supervisor_id' => ['nullable', 'integer'],
        ]);

        $supervisorId = $validated['supervisor_id'];

        if ($supervisorId !== null && $this->wouldCreateCycle($id, (int) $supervisorId)) {
            return response()->json(['message' => 'Supervisor assignment would create a cycle.'], 422);
        }

        $profile = UserProfile::firstOrNew(['user_id' => $id]);
        $profile->supervisor_id = $supervisorId;
        $profile->save();

        return response()->json(['message' => 'Supervisor updated.']);
    }

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();

        // Only ACL 9 can reset passwords.
        $actorProfile = UserProfile::where('user_id', $actor->id)->first();
        if ($actorProfile === null || (int) $actorProfile->acl_id !== 9) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:12'],
            'confirm' => ['required', 'string', 'same:password'],
        ]);

        $profile = UserProfile::firstOrNew(['user_id' => $id]);
        $profile->password_v2 = Hash::make($validated['password']);
        $profile->save();

        AdminAudit::create([
            'actor_user_id' => $actor->id,
            'action' => 'reset_password',
            'entity' => 'user',
            'entity_id' => $id,
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => 'Password reset.']);
    }

    public function softDelete(int $id): JsonResponse
    {
        $protectedAdmins = [334, 2194, 2415, 3040, 4735, 4862, 4991, 4992];

        if (in_array($id, $protectedAdmins, true)) {
            return response()->json(['message' => 'Protected administrator cannot be deleted.'], 403);
        }

        $profile = UserProfile::where('user_id', $id)->first();

        if ($profile === null) {
            return response()->json(['message' => 'User profile not found.'], 404);
        }

        $profile->delete();

        return response()->json(['message' => 'User disabled.']);
    }

    public function supervisorTree(int $id): JsonResponse
    {
        $tree = [];
        $current = $id;
        $depth = 0;

        while ($current !== null && $depth < 10) {
            $profile = UserProfile::where('user_id', $current)->first();

            if ($profile === null) {
                break;
            }

            $tree[] = [
                'user_id' => $current,
                'supervisor_id' => $profile->supervisor_id,
            ];

            $current = $profile->supervisor_id;
            $depth++;
        }

        return response()->json($tree);
    }

    private function wouldCreateCycle(int $userId, int $supervisorId): bool
    {
        $current = $supervisorId;
        $depth = 0;

        while ($current !== null && $depth < 10) {
            if ($current === $userId) {
                return true;
            }

            $profile = UserProfile::where('user_id', $current)->first();
            $current = $profile?->supervisor_id;
            $depth++;
        }

        return false;
    }
}
