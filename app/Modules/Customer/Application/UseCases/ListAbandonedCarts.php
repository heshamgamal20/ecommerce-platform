<?php
namespace App\Modules\Customer\Application\UseCases;

use App\Modules\Customer\Domain\Contracts\CartRepositoryInterface;

final class ListAbandonedCarts
{
    public function __construct(private readonly CartRepositoryInterface $carts) {}

    public function execute(int $days, int $perPage): object
    {
        return $this->carts->listAbandoned($days, $perPage);
    }
}
