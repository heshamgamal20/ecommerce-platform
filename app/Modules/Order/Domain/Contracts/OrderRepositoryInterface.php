<?php

namespace App\Modules\Order\Domain\Contracts;

interface OrderRepositoryInterface
{


    public function listForUser(int $userId, int $perPage = 25): iterable;

    public function listAll(int $perPage = 25): iterable;

    public function findForUser(int $userId, int $orderId): object;

    public function find(int $orderId): object;

    public function updateStatus(int $orderId, string $status): object;

    public function cancelForUser(int $userId, int $orderId): object;

    public function cancel(int $orderId): object;

    public function addShippingFee(int $orderId, int $fee, int $total): object;

    public function markRefunded(int $orderId): object;
}
