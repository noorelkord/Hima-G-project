<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    public static function send(int $userId, string $title, string $body, string $type, int $relatedId = null): void
    {
        Notification::create([
            'user_id'    => $userId,
            'title'      => $title,
            'body'       => $body,
            'type'       => $type,
            'related_id' => $relatedId,
            'is_read'    => false,
        ]);
    }
}