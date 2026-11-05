<?php

namespace App\Http\Controllers;

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
}
