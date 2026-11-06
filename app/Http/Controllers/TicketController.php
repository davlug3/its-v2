<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\UserProfile;
use App\Services\SupervisorService;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function __construct(
        private TicketService $tickets,
        private SupervisorService $supervisors,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $scope = (string) $request->query('scope', 'mine');

        if ($scope === 'all' && $this->aclOf($request) >= 2) {
            $filters = [
                'state_id' => $request->query('state_id'),
                'category_id' => $request->query('category_id'),
                'assigned_uid' => $request->query('assigned_uid'),
                'created_uid' => $request->query('created_uid'),
                'search' => $request->query('search'),
            ];

            $result = $this->tickets->listAll($page, $perPage, $filters);

            return response()->json($result);
        }

        $stateId = $request->query('state_id');
        $stateId = $stateId === null ? null : (int) $stateId;

        $result = $this->tickets->listForUser((int) $user->id, $page, $perPage, $stateId);

        return response()->json($result);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $ticket = Ticket::find($id);
        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        // Only helpdesk/admin can assign; end users cannot.
        $canAssign = $this->aclOf($request) >= 3;

        $result = $this->tickets->assign($id, $request->all(), (int) $user->id, $canAssign);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => $result['message']]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $result = $this->tickets->create(
            $request->all(),
            (int) $user->id,
            true,
        );

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'ticket_id' => $result['ticket_id'],
        ], 201);
    }

    public function endUserStore(Request $request): JsonResponse
    {
        $user = $request->user();

        $result = $this->tickets->create(
            $request->all(),
            (int) $user->id,
            false,
        );

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'ticket_id' => $result['ticket_id'],
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $ticket = Ticket::find($id);
        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $canEdit = $this->aclOf($request) >= 3 || $ticket->created_uid === (int) $user->id;

        if (! $canEdit) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $result = $this->tickets->edit($id, $request->all(), (int) $user->id);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => $result['message']]);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Permission check: creator, assigned user, or ACL >= 3.
        $ticket = Ticket::find($id);
        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $canClose = $ticket->created_uid === (int) $user->id
            || $ticket->assigned_uid === (int) $user->id
            || $this->aclOf($request) >= 3;

        $result = $this->tickets->close($id, (int) $user->id, $canClose);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => $result['message']]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::find($id);

        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $userId = (int) $request->user()->id;

        $canView = $this->aclOf($request) >= 3
            || $ticket->created_uid === $userId
            || $ticket->assigned_uid === $userId
            || $ticket->eu_uid === $userId;

        if (! $canView) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($ticket);
    }

    public function logs(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::find($id);

        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $userId = (int) $request->user()->id;
        $canView = $this->aclOf($request) >= 3
            || $ticket->created_uid === $userId
            || $ticket->assigned_uid === $userId
            || $ticket->eu_uid === $userId;

        if (! $canView) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($this->tickets->logs($id));
    }

    public function comment(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::find($id);

        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $userId = (int) $request->user()->id;
        $canView = $this->aclOf($request) >= 3
            || $ticket->created_uid === $userId
            || $ticket->assigned_uid === $userId
            || $ticket->eu_uid === $userId;

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:4000'],
        ]);

        $result = $this->tickets->comment($id, (string) $validated['text'], $userId, $canView);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => $result['message']]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->id;
        $acl = $this->aclOf($request);

        $ticket = Ticket::find($id);
        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $isSup = $this->supervisors->isSupervisor($userId, (int) $ticket->created_uid, $acl);

        $result = $this->tickets->approve(
            $id,
            (string) $request->input('remarks', ''),
            $userId,
            $acl,
            $isSup,
        );

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => $result['message']]);
    }

    public function disapprove(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->id;
        $acl = $this->aclOf($request);

        $ticket = Ticket::find($id);
        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $isSup = $this->supervisors->isSupervisor($userId, (int) $ticket->created_uid, $acl);

        $result = $this->tickets->disapprove(
            $id,
            (string) $request->input('remarks', ''),
            $userId,
            $acl,
            $isSup,
        );

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => $result['message']]);
    }

    public function undoApprove(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->id;
        $acl = $this->aclOf($request);

        $ticket = Ticket::find($id);
        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        $isSup = $this->supervisors->isSupervisor($userId, (int) $ticket->created_uid, $acl);

        $result = $this->tickets->undoApprove($id, $userId, $acl, $isSup);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => $result['message']]);
    }

    public function batchApprove(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->id;
        $acl = $this->aclOf($request);

        $ids = $request->input('ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }

        $allowed = $acl >= 9 ? [] : $this->supervisors->allSubordinates($userId);

        $result = $this->tickets->batchApprove(
            $ids,
            (string) $request->input('remarks', ''),
            $userId,
            $acl,
            $allowed,
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'errors' => $result['errors'],
        ]);
    }

    public function batchDisapprove(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->id;
        $acl = $this->aclOf($request);

        $ids = $request->input('ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }

        $allowed = $acl >= 9 ? [] : $this->supervisors->allSubordinates($userId);

        $result = $this->tickets->batchDisapprove(
            $ids,
            (string) $request->input('remarks', ''),
            $userId,
            $acl,
            $allowed,
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'errors' => $result['errors'],
        ]);
    }

    public function bySubordinates(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->id;
        $acl = $this->aclOf($request);

        if ($acl < 3) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $indirect = filter_var($request->query('indirect', false), FILTER_VALIDATE_BOOLEAN);
        $hideOld = filter_var($request->query('hide_old', true), FILTER_VALIDATE_BOOLEAN);

        $direct = $this->supervisors->directSubordinates($userId);
        $subordinates = $indirect
            ? $this->supervisors->allSubordinates($userId)
            : $direct;

        $hideCutoff = $this->cutoff('approval.hide_old_cutoff', 1784692800);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 40)));

        $result = $this->tickets->bySubordinates(
            $subordinates,
            $hideOld,
            $page,
            $perPage,
            $hideCutoff,
        );

        return response()->json($result);
    }

    public function pendingReviewCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->id;
        $acl = $this->aclOf($request);

        if ($acl < 3) {
            return response()->json(['count' => 0]);
        }

        $direct = $this->supervisors->directSubordinates($userId);
        $badgeCutoff = $this->cutoff('approval.badge_cutoff', 1784553600);

        $count = $this->tickets->pendingReviewCount($direct, $badgeCutoff);

        return response()->json(['count' => $count]);
    }

    private function cutoff(string $name, int $default): int
    {
        $row = DB::connection('mysql')
            ->table('application_settings')
            ->where('name', $name)
            ->first();

        if ($row === null) {
            return $default;
        }

        $value = (int) $row->setting;

        return $value > 0 ? $value : $default;
    }

    private function aclOf(Request $request): int
    {
        $user = $request->user();

        if ($user === null) {
            return 1;
        }

        $profile = UserProfile::where('user_id', $user->id)->first();

        return $profile !== null ? (int) $profile->acl_id : 1;
    }
}
