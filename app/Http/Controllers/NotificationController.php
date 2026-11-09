<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->id;

        $items = Notification::where('user_id', $userId)
            ->orderByDesc('dt')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (Notification $n): array {
                $arr = $n->toArray();
                $arr['subject'] = $this->sanitizeText((string) ($arr['subject'] ?? ''));
                $arr['data'] = $this->sanitizeText((string) ($arr['data'] ?? ''));

                return $arr;
            })
            ->values();

        return response()->json($items);
    }

    private function sanitizeText(string $text): string
    {
        // Defense-in-depth: redact credential-like patterns in legacy rows.
        $text = preg_replace('/(password|pword|passwd|pwd)\s*[:=]\s*[^\s&<"\']+/i', '$1=[redacted]', $text) ?? $text;
        $text = str_replace(['http://http://', 'https://https://'], ['http://', 'https://'], $text);

        return $text;
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $count = (int) Notification::where('user_id', $userId)
            ->where('state', 1)
            ->count();

        return response()->json(['count' => $count]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $notification = Notification::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if ($notification === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $notification->state = 2;
        $notification->save();

        return response()->json(['message' => 'Marked read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        Notification::where('user_id', $userId)
            ->where('state', 1)
            ->update(['state' => 2]);

        return response()->json(['message' => 'All marked read.']);
    }
}
