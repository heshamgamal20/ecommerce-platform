<?php

namespace App\Modules\Administration\Application\Services;

use App\Models\AdminNotification;
use App\Models\User;

final class AdminNotificationService
{
    public function notify(string $type, string $title, string $body, array $data = [], ?string $dedupeKey = null): int
    {
        $recipients = User::query()->whereHas('roles.permissions', fn ($query) => $query->where('slug', 'admin.notifications.view'))->pluck('id');
        $created = 0;
        foreach ($recipients as $userId) {
            $notification = AdminNotification::query()->firstOrCreate(
                ['user_id' => $userId, 'dedupe_key' => $dedupeKey ?: $type.':'.sha1($title.$body.json_encode($data))],
                ['type' => $type, 'title' => $title, 'body' => $body, 'data' => $data],
            );
            $created += $notification->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }
}
