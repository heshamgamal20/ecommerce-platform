<?php

namespace Tests\Feature;

use App\Models\CustomerNotification;
use App\Models\CustomerPreference;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerNotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_customer_can_list_filter_count_and_read_own_notifications_only(): void
    {
        $customer = User::factory()->create();
        $customer->roles()->attach(Role::query()->where('slug', 'customer')->firstOrFail());
        $other = User::factory()->create();
        $unread = CustomerNotification::query()->create([
            'user_id' => $customer->id,
            'type' => 'order.created',
            'title' => 'Order created',
            'body' => 'Your order was received.',
        ]);
        CustomerNotification::query()->create([
            'user_id' => $customer->id,
            'type' => 'promotion',
            'title' => 'Promotion',
            'read_at' => now(),
        ]);
        CustomerNotification::query()->create([
            'user_id' => $other->id,
            'type' => 'private',
            'title' => 'Private',
        ]);

        $this->actingAs($customer)
            ->getJson('/api/v1/customer/notifications?unread=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unread->id)
            ->assertJsonPath('unread_count', 1)
            ->assertJsonMissing(['title' => 'Private']);

        $this->actingAs($customer)
            ->getJson('/api/v1/customer/notifications/count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->actingAs($customer)
            ->patchJson("/api/v1/customer/notifications/{$unread->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read_at', fn ($value): bool => $value !== null);

        $this->actingAs($customer)
            ->patchJson("/api/v1/customer/notifications/" . CustomerNotification::query()->where('user_id', $other->id)->value('id') . "/read")
            ->assertNotFound();
    }

    public function test_customer_can_mark_all_own_notifications_as_read(): void
    {
        $customer = User::factory()->create();
        $customer->roles()->attach(Role::query()->where('slug', 'customer')->firstOrFail());
        CustomerNotification::query()->create(['user_id' => $customer->id, 'type' => 'a', 'title' => 'A']);
        CustomerNotification::query()->create(['user_id' => $customer->id, 'type' => 'b', 'title' => 'B']);

        $this->actingAs($customer)
            ->patchJson('/api/v1/customer/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.marked_as_read', 2)
            ->assertJsonPath('data.unread_count', 0);

        $this->assertDatabaseCount('customer_notifications', 2);
        $this->assertDatabaseMissing('customer_notifications', ['user_id' => $customer->id, 'read_at' => null]);
    }

    public function test_notification_preferences_can_disable_a_type_or_category(): void
    {
        $customer = User::factory()->create();
        CustomerPreference::query()->create([
            'user_id' => $customer->id,
            'data' => ['notifications' => ['payment' => false, 'order.created' => false]],
        ]);

        $repository = app(\App\Modules\Customer\Domain\Contracts\CustomerNotificationRepositoryInterface::class);
        self::assertNull($repository->createForUser($customer->id, 'payment.failed', 'Payment failed'));
        self::assertNull($repository->createForUser($customer->id, 'order.created', 'Order received'));
        self::assertNotNull($repository->createForUser($customer->id, 'shipment.delivered', 'Shipment delivered'));
        $this->assertDatabaseCount('customer_notifications', 1);
    }
}
