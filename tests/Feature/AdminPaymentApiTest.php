<?php

namespace Tests\Feature;

use App\Models\CustomerOrder;
use App\Models\Payment;
use App\Models\PaymentOperation;
use App\Models\PaymentWebhookEvent;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_filter_payments_and_view_operations_and_webhooks(): void
    {
        $this->seed(RbacSeeder::class);
        $manager = $this->userWithRole('manager');
        $customer = $this->userWithRole('customer');
        $order = CustomerOrder::query()->create(['user_id' => $customer->id, 'status' => 'confirmed', 'total_amount' => 700, 'currency' => 'EGP', 'shipping_address' => ['city' => 'Cairo']]);
        $payment = Payment::query()->create(['order_id' => $order->id, 'user_id' => $customer->id, 'method' => 'card', 'provider_reference' => 'pay-ref-1', 'amount' => 700, 'currency' => 'EGP', 'status' => 'failed', 'idempotency_key' => 'admin-payment-1']);
        PaymentOperation::query()->create(['payment_id' => $payment->id, 'operation' => 'create', 'status' => 'failed', 'idempotency_key' => 'operation-1', 'attempt_count' => 2, 'last_error' => 'Provider timeout']);
        PaymentWebhookEvent::query()->create(['provider' => 'paymob', 'event_id' => 'event-1', 'event_type' => 'payment.failed', 'status' => 'processed', 'payment_reference' => 'pay-ref-1', 'payload' => ['id' => 1]]);

        $this->actingAs($manager)->getJson('/api/v1/admin/payments?status=failed&method=card')->assertOk()->assertJsonPath('data.data.0.id', $payment->id)->assertJsonPath('data.data.0.operations_count', 1);
        $this->actingAs($manager)->getJson('/api/v1/admin/payments/'.$payment->id)->assertOk()->assertJsonPath('data.payment.id', $payment->id)->assertJsonPath('data.webhooks.0.event_type', 'payment.failed');
        $this->actingAs($manager)->getJson('/api/v1/admin/payments/exceptions')->assertOk()->assertJsonPath('data.data.0.id', $payment->id)->assertJsonPath('data.data.0.operations.0.last_error', 'Provider timeout');
    }

    public function test_customer_cannot_access_admin_payments(): void
    {
        $this->seed(RbacSeeder::class);
        $customer = $this->userWithRole('customer');
        $this->actingAs($customer)->getJson('/api/v1/admin/payments')->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());
        return $user;
    }
}
