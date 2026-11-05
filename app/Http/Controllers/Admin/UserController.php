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

        $query = DB::connection('payroll')
            ->table('user as u')
            ->join('payroll as p', 'u.id', '=', 'p.id')
            ->leftJoin('user_profile as up', 'u.id', '=', 'up.user_id')
            ->select([
                'u.id',
                'u.username',
                'p.name',
                'p.mname',
                'p.lname',
                'p.department',
                'p.status',
                'up.acl_id',
                'up.group_id',
                'up.fpc',
                'up.login_attempts',
                'up.last_login_dt',
                'up.supervisor_id',
                'up.deleted_at',
            ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('u.username', 'like', '%'.$search.'%')
                    ->orWhere('p.name', 'like', '%'.$search.'%')
                    ->orWhere('p.lname', 'like', '%'.$search.'%');
            });
        }

        if ($acl !== null && $acl !== '') {
            $query->where('up.acl_id', (int) $acl);
        }

        $users = $query->orderBy('p.lname')->paginate(40);

        return response()->json($users);
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

    public function toggleFpc(Request $request, int $id): JsonResponse
    {
        $profile = UserProfile::firstOrNew(['user_id' => $id]);
        $profile->fpc = $profile->fpc === 1 ? 2 : 1;
        $profile->save();

        return response()->json(['fpc' => $profile->fpc]);
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
            'force_change' => ['boolean'],
        ]);

        $forceChange = (bool) ($validated['force_change'] ?? true);

        $profile = UserProfile::firstOrNew(['user_id' => $id]);
        $profile->password_v2 = Hash::make($validated['password']);
        $profile->fpc = $forceChange ? 1 : 2;
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
