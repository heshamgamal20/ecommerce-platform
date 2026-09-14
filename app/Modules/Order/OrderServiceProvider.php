<?php

namespace App\Modules\Order;

use App\Modules\Order\Domain\Contracts\OrderRepositoryInterface;
use App\Modules\Order\Domain\Contracts\CheckoutOrderWriterInterface;
use App\Modules\Order\Domain\Contracts\PricingCalculatorInterface;
use App\Modules\Order\Infrastructure\Persistence\EloquentCheckoutOrderWriter;
use App\Modules\Order\Infrastructure\Pricing\DefaultPricingCalculator;
use App\Modules\Order\Domain\Contracts\ReturnRepositoryInterface;
use App\Modules\Order\Domain\Contracts\InvoiceRepositoryInterface;
use App\Modules\Order\Domain\Contracts\TransactionManagerInterface;
use App\Modules\Order\Infrastructure\Persistence\DatabaseTransactionManager;
use App\Modules\Order\Infrastructure\Persistence\EloquentOrderRepository;
use App\Modules\Order\Infrastructure\Persistence\EloquentReturnRepository;
use App\Modules\Order\Infrastructure\Persistence\EloquentInvoiceRepository;
use Illuminate\Support\ServiceProvider;

final class OrderServiceProvider extends ServiceProvider
{
    public array $bindings = [
        OrderRepositoryInterface::class => EloquentOrderRepository::class,
        CheckoutOrderWriterInterface::class => EloquentCheckoutOrderWriter::class,
        PricingCalculatorInterface::class => DefaultPricingCalculator::class,
        ReturnRepositoryInterface::class => EloquentReturnRepository::class,
        InvoiceRepositoryInterface::class => EloquentInvoiceRepository::class,
        TransactionManagerInterface::class => DatabaseTransactionManager::class,
    ];
}
