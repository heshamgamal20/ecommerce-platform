<?php

namespace Tests\Unit;

use App\Modules\Order\Domain\Exceptions\InvalidOrderStatusTransitionException;
use App\Modules\Order\Domain\OrderLifecycle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrderLifecycleTest extends TestCase
{
    #[DataProvider('validTransitions')]
    public function test_valid_status_transitions_are_allowed(string $from, string $to): void
    {
        OrderLifecycle::assertCanTransition($from, $to);
        self::assertTrue(true);
    }

    public function test_invalid_transition_raises_domain_exception(): void
    {
        $this->expectException(InvalidOrderStatusTransitionException::class);

        OrderLifecycle::assertCanTransition('pending', 'shipped');
    }

    public function test_only_active_orders_can_be_cancelled(): void
    {
        self::assertTrue(OrderLifecycle::canCancel('pending'));
        self::assertTrue(OrderLifecycle::canCancel('confirmed'));
        self::assertTrue(OrderLifecycle::canCancel('processing'));
        self::assertFalse(OrderLifecycle::canCancel('shipped'));
        self::assertFalse(OrderLifecycle::canCancel('delivered'));
    }

    public static function validTransitions(): iterable
    {
        yield ['pending', 'confirmed'];
        yield ['confirmed', 'processing'];
        yield ['processing', 'shipped'];
        yield ['shipped', 'delivered'];
        yield ['delivered', 'refunded'];
    }
}
