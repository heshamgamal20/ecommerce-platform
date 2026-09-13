<?php

namespace App\Modules\Customer\Domain\Contracts;

interface CustomerNotificationRepositoryInterface
{
    /** @return iterable<int, object> */
    public function listForUser(int $userId, bool $unreadOnly = false, int $perPage = 25): object;

    public function unreadCountForUser(int $userId): int;

    public function createForUser(int $userId, string $type, string $title, ?string $body = null): ?object;

    public function markAsRead(int $userId, int $notificationId): object;

    public function markAllAsRead(int $userId): int;
}
