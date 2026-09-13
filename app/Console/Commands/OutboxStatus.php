<?php

namespace App\Console\Commands;

use App\Models\OutboxEvent;
use Illuminate\Console\Command;

final class OutboxStatus extends Command
{
    protected $signature = 'outbox:status {--stuck=10 : Minimum attempts for high-attempt warning}';
    protected $description = 'Show Outbox status counts and high-attempt events';

    public function handle(): int
    {
        $counts = OutboxEvent::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        foreach ($counts as $status => $total) {
            $this->line(sprintf('%s: %d', $status, $total));
        }
        $stuck = OutboxEvent::query()->whereIn('status', ['pending', 'processing'])
            ->where('attempt_count', '>=', (int) $this->option('stuck'))
            ->orderByDesc('attempt_count')->get(['id', 'event_type', 'status', 'attempt_count', 'last_error']);
        if ($stuck->isNotEmpty()) {
            $this->warn('High-attempt events:');
            $this->table(['id', 'event_type', 'status', 'attempts', 'last_error'], $stuck->map(fn (OutboxEvent $event) => [$event->id, $event->event_type, $event->status, $event->attempt_count, $event->last_error])->all());
        }
        return self::SUCCESS;
    }
}
