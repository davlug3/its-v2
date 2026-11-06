<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\UserProfile;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(private TicketService $tickets) {}

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
