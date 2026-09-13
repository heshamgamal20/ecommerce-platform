<?php
namespace App\Modules\Catalog\Domain\Contracts;

interface CheckoutProductReaderInterface
{
    public function findForCheckout(int $productId): ?object;
}
