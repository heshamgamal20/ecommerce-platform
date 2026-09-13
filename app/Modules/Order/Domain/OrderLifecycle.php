<?php

namespace App\Modules\Order\Domain;

use App\Modules\Order\Domain\Exceptions\InvalidOrderStatusTransitionException;

final class OrderLifecycle
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled', 'refunded'],
        'processing' => ['shipped', 'cancelled', 'refunded'],
        'shipped' => ['delivered', 'refunded'],
        'delivered' => ['refunded'],
        'cancelled' => ['refunded'],
        'refunded' => [],
    ];

    public static function assertCanTransition(string $from, string $to): void
    {
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw InvalidOrderStatusTransitionException::from($from, $to);
        }
    }

    public static function canCancel(string $status): bool
    {
        return in_array($status, ['pending', 'confirmed', 'processing'], true);
    }

    public static function statuses(): array
    {
        return array_keys(self::TRANSITIONS);
    }
}
