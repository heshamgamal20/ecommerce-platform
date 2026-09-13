<?php

namespace App\Modules\Customer\Infrastructure\Persistence;

use App\Models\CustomerNotification;
use App\Models\CustomerPreference;
use App\Modules\Customer\Domain\Contracts\CustomerNotificationRepositoryInterface;
use App\Modules\Customer\Domain\Exceptions\CustomerFeatureNotFoundException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentCustomerNotificationRepository implements CustomerNotificationRepositoryInterface
{
    public function listForUser(int $userId, bool $unreadOnly = false): iterable
    {
        return CustomerNotification::query()
            ->where('user_id', $userId)
            ->when($unreadOnly, static fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->get();
    }

    public function unreadCountForUser(int $userId): int
    {
        return CustomerNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function createForUser(int $userId, string $type, string $title, ?string $body = null): ?object
    {
        $preferences = CustomerPreference::query()->where('user_id', $userId)->value('data');
        $notificationPreferences = is_array($preferences) ? ($preferences['notifications'] ?? []) : [];
        if (is_array($notificationPreferences)) {
            $category = (string) str($type)->before('.');
            $enabled = $notificationPreferences[$type]
                ?? $notificationPreferences[$category]
                ?? $notificationPreferences['*']
                ?? true;
            if ($enabled === false) {
                return null;
            }
        }

        return CustomerNotification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
        ]);
    }

    public function markAsRead(int $userId, int $notificationId): object
    {
        try {
            $notification = CustomerNotification::query()
                ->where('user_id', $userId)
                ->findOrFail($notificationId);
        } catch (ModelNotFoundException) {
            throw new CustomerFeatureNotFoundException('Notification', $notificationId);
        }

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return $notification->fresh();
    }

    public function markAllAsRead(int $userId): int
    {
        return CustomerNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
