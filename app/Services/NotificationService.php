<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Carbon;

class NotificationService
{
    public function notify(int $userId, string $subject, string $data): void
    {
        $notification = new Notification;
        $notification->user_id = $userId;
        $notification->subject = mb_substr($subject, 0, 255);
        $notification->data = $data;
        $notification->dt = Carbon::now('Asia/Manila')->startOfDay()->timestamp;
        $notification->state = 1;
        $notification->save();
    }
}
