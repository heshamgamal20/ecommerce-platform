<?php

namespace Tests\Feature;

use App\Models\OutboxEvent;
use App\Modules\Shared\Domain\Contracts\OutboxEventRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OutboxLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_failures_move_outbox_event_to_dead_letter(): void
    {
        $event = OutboxEvent::query()->create([
            'aggregate_type' => 'payment',
            'aggregate_id' => 1,
            'event_type' => 'payment.create',
            'deduplication_key' => 'test:dead-letter',
            'status' => 'processing',
            'payload' => ['test' => true],
        ]);
        $repository = app(OutboxEventRepositoryInterface::class);

        for ($attempt = 1; $attempt <= (int) config('outbox.max_attempts', 10); $attempt++) {
            $repository->markFailed($event->deduplication_key, 'provider unavailable');
        }

        $event->refresh();
        self::assertSame('dead_letter', $event->status);
        self::assertSame((int) config('outbox.max_attempts', 10), $event->attempt_count);
        self::assertNotNull($event->dead_lettered_at);
        self::assertNull($event->next_attempt_at);
    }

    public function test_dead_letter_event_can_be_replayed_and_reset(): void
    {
        $event = OutboxEvent::query()->create([
            'aggregate_type' => 'shipment',
            'aggregate_id' => 1,
            'event_type' => 'shipment.create',
            'deduplication_key' => 'test:replay',
            'status' => 'dead_letter',
            'attempt_count' => 10,
            'payload' => ['test' => true],
            'last_error' => 'permanent failure',
            'dead_lettered_at' => now(),
        ]);

        self::assertTrue(app(OutboxEventRepositoryInterface::class)->replay($event->id));
        $event->refresh();
        self::assertSame('pending', $event->status);
        self::assertSame(0, $event->attempt_count);
        self::assertNull($event->last_error);
        self::assertNull($event->dead_lettered_at);
        self::assertNotNull($event->next_attempt_at);
    }
}
