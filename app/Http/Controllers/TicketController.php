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

        $profile = UserProfile::where('user_id', $user->id)->first();
        $acl = $profile->acl_id ?? 1;
        $canClose = $ticket->created_uid === (int) $user->id
            || $ticket->assigned_uid === (int) $user->id
            || $acl >= 3;

        $result = $this->tickets->close($id, (int) $user->id, $canClose);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => $result['message']]);
    }

    public function show(int $id): JsonResponse
    {
        $ticket = Ticket::find($id);

        if ($ticket === null) {
            return response()->json(['message' => 'Ticket not found.'], 404);
        }

        return response()->json($ticket);
    }
}
