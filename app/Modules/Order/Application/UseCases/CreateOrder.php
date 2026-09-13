<?php

namespace App\Modules\Order\Application\UseCases;

use App\Modules\Order\Domain\ValueObjects\CheckoutData;

final class CreateOrder
{
    public function __construct(private readonly Checkout $checkout) {}

    public function execute(CheckoutData $data): object
    {
        return $this->checkout->execute($data);
    }
}
