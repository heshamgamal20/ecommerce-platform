<?php
namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Models\Product;
use App\Modules\Catalog\Domain\Contracts\CheckoutProductReaderInterface;

final class EloquentCheckoutProductReader implements CheckoutProductReaderInterface
{
    public function findForCheckout(int $productId): ?object
    {
        return Product::query()->with('variants')->find($productId);
    }
}
