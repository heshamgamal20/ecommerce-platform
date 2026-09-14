<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\CustomerOrder;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class AdminNotificationAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_creates_deduplicated_payment_failure_notification_for_admins(): void
    {
        $this->seed(RbacSeeder::class);
        $owner = User::factory()->create();
        $owner->roles()->attach(Role::query()->where('slug', 'owner')->firstOrFail());
        $order = CustomerOrder::query()->create(['user_id' => $owner->id, 'status' => 'confirmed', 'total_amount' => 100, 'currency' => 'EGP', 'shipping_address' => ['city' => 'Cairo']]);
        $payment = Payment::query()->create(['order_id' => $order->id, 'user_id' => $owner->id, 'method' => 'card', 'amount' => 100, 'currency' => 'EGP', 'status' => 'failed', 'idempotency_key' => 'notification-payment']);

        Artisan::call('admin:notifications:scan');
        Artisan::call('admin:notifications:scan');

        $this->assertDatabaseCount('admin_notifications', 1);
        $this->assertDatabaseHas('admin_notifications', ['user_id' => $owner->id, 'type' => 'payment.failed']);
        $this->assertSame(1, AdminNotification::query()->where('dedupe_key', 'like', 'payment.failed:'.$payment->id.':%')->count());
    }
}
