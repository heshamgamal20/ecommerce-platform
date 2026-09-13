<?php

namespace App\Modules\Customer\Application\UseCases;

use App\Modules\Auth\Domain\Contracts\AuthenticationServiceInterface;
use App\Modules\Auth\Domain\Exceptions\AuthenticationException;
use App\Modules\Customer\Domain\Contracts\CustomerNotificationRepositoryInterface;

final class ManageCustomerNotifications
{
    public function __construct(
        private readonly AuthenticationServiceInterface $auth,
        private readonly CustomerNotificationRepositoryInterface $repo,
    ) {
    }

    private function id(): int
    {
        $user = $this->auth->user();
        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return $user->id;
    }

    public function list(bool $unreadOnly = false, int $perPage = 25): object
    {
        return $this->repo->listForUser($this->id(), $unreadOnly, $perPage);
    }

    public function unreadCount(): int
    {
        return $this->repo->unreadCountForUser($this->id());
    }

    public function read(int $id): object
    {
        return $this->repo->markAsRead($this->id(), $id);
    }

    public function readAll(): int
    {
        return $this->repo->markAllAsRead($this->id());
    }
}
