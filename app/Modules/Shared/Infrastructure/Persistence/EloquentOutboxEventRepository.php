<?php

namespace App\Modules\Shared\Infrastructure\Persistence;

use App\Models\OutboxEvent;
use App\Modules\Shared\Domain\Contracts\OutboxEventRepositoryInterface;

final class EloquentOutboxEventRepository implements OutboxEventRepositoryInterface
{
    public function markDispatched(string $deduplicationKey): void
    {
        OutboxEvent::query()->where('deduplication_key', $deduplicationKey)->update([
            'status' => 'dispatched',
            'dispatched_at' => now(),
            'last_error' => null,
            'dead_lettered_at' => null,
        ]);
    }

    public function markFailed(string $deduplicationKey, string $error): void
    {
        $event = OutboxEvent::query()->where('deduplication_key', $deduplicationKey)->first();
        if ($event === null) {
            return;
        }
        $attempts = ((int) $event->attempt_count) + 1;
        $deadLetter = $attempts >= (int) config('outbox.max_attempts', 10);
        $event->update([
            'status' => $deadLetter ? 'dead_letter' : 'pending',
            'attempt_count' => $attempts,
            'last_error' => $error,
            'next_attempt_at' => $deadLetter ? null : now()->addMinutes((int) config('outbox.retry_delay_minutes', 5)),
            'dead_lettered_at' => $deadLetter ? now() : null,
        ]);
    }

    public function replay(int $eventId): bool
    {
        return OutboxEvent::query()->whereKey($eventId)->where('status', 'dead_letter')->update([
            'status' => 'pending',
            'attempt_count' => 0,
            'last_error' => null,
            'next_attempt_at' => now(),
            'dead_lettered_at' => null,
        ]) === 1;
    }
}
