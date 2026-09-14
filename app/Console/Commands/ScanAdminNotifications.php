<?php

namespace App\Console\Commands;

use App\Models\CarrierSettlement;
use App\Models\InventoryItem;
use App\Models\OutboxEvent;
use App\Models\Payment;
use App\Models\Shipment;
use App\Modules\Administration\Application\Services\AdminNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ScanAdminNotifications extends Command
{
    protected $signature = 'admin:notifications:scan';

    protected $description = 'Create deduplicated admin notifications for operational failures and risks';

    public function handle(AdminNotificationService $notifications): int
    {
        Payment::query()->whereIn('status', ['failed', 'abandoned'])->where('updated_at', '>=', now()->subMinutes(15))->each(fn ($payment) => $notifications->notify('payment.failed', 'Payment failed', 'Payment #'.$payment->id.' requires review.', ['payment_id' => $payment->id], 'payment.failed:'.$payment->id.':'.$payment->updated_at->timestamp));
        Shipment::query()->whereIn('status', ['failed', 'cancelled'])->where('updated_at', '>=', now()->subMinutes(15))->each(fn ($shipment) => $notifications->notify('shipment.failed', 'Shipment failed', 'Shipment #'.$shipment->id.' requires review.', ['shipment_id' => $shipment->id], 'shipment.failed:'.$shipment->id.':'.$shipment->updated_at->timestamp));
        OutboxEvent::query()->where('status', 'dead_letter')->each(fn ($event) => $notifications->notify('outbox.dead_letter', 'Outbox event failed permanently', 'Outbox event #'.$event->id.' is in dead letter.', ['event_id' => $event->id, 'error' => $event->last_error], 'outbox.dead_letter:'.$event->id.':'.$event->updated_at->timestamp));
        DB::table('failed_jobs')->where('failed_at', '>=', now()->subMinutes(15))->get()->each(fn ($job) => $notifications->notify('job.failed', 'Queue job failed', 'A queue job failed and requires review.', ['job_id' => $job->uuid], 'job.failed:'.$job->uuid));
        CarrierSettlement::query()->where('status', 'disputed')->each(fn ($settlement) => $notifications->notify('settlement.disputed', 'Carrier settlement has a difference', 'Settlement #'.$settlement->id.' has an unresolved difference.', ['settlement_id' => $settlement->id, 'difference' => $settlement->difference], 'settlement.disputed:'.$settlement->id.':'.$settlement->updated_at->timestamp));
        $threshold = (int) config('inventory.low_stock_threshold', 5);
        InventoryItem::query()->whereRaw('(on_hand - reserved) <= ?', [$threshold])->each(fn ($item) => $notifications->notify('inventory.low_stock', 'Low inventory', 'Inventory item #'.$item->id.' is below the low-stock threshold.', ['inventory_item_id' => $item->id, 'available' => (int) $item->on_hand - (int) $item->reserved], 'inventory.low_stock:'.$item->id.':'.((int) $item->on_hand - (int) $item->reserved)));
        $this->info('Admin notifications scan completed.');

        return self::SUCCESS;
    }
}
