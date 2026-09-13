<?php

namespace App\Modules\Customer\Application\UseCases;

use App\Modules\Customer\Domain\Contracts\CustomerNotificationRepositoryInterface;

final class CreateCustomerNotification
{
    public function __construct(
        private readonly CustomerNotificationRepositoryInterface $notifications,
    ) {
    }

    public function execute(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
    ): ?object {
        return $this->notifications->createForUser($userId, $type, $title, $body);
    }
}
