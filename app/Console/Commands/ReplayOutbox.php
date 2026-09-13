<?php

namespace App\Console\Commands;

use App\Models\OutboxEvent;
use App\Modules\Shared\Application\Jobs\ProcessOutboxEvent;
use App\Modules\Shared\Domain\Contracts\OutboxEventRepositoryInterface;
use Illuminate\Console\Command;

final class ReplayOutbox extends Command
{
    protected $signature = 'outbox:replay {eventId : Dead-letter event ID}';
    protected $description = 'Reset and enqueue one dead-letter Outbox event';

    public function handle(OutboxEventRepositoryInterface $outbox): int
    {
        $eventId = (int) $this->argument('eventId');
        if (OutboxEvent::query()->whereKey($eventId)->where('status', 'dead_letter')->doesntExist()) {
            $this->error('Only an existing dead-letter event can be replayed.');
            return self::FAILURE;
        }
        if (! $outbox->replay($eventId)) {
            $this->error('The event could not be replayed.');
            return self::FAILURE;
        }
        ProcessOutboxEvent::dispatch($eventId);
        $this->info("Replayed Outbox event {$eventId}.");
        return self::SUCCESS;
    }
}
