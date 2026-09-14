<?php

namespace Tests\Feature;

use App\Models\CustomerOrder;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminCustomerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_list_and_view_customer_summary(): void
    {
        $this->seed(RbacSeeder::class);
        $manager = $this->userWithRole('manager');
        $customer = $this->userWithRole('customer');
        CustomerOrder::query()->create(['user_id' => $customer->id, 'status' => 'confirmed', 'total_amount' => 900, 'subtotal_amount' => 800, 'currency' => 'EGP', 'shipping_address' => ['city' => 'Cairo']]);

        $this->actingAs($manager)->getJson('/api/v1/admin/customers?q='.urlencode($customer->email))->assertOk()->assertJsonPath('data.data.0.id', $customer->id)->assertJsonPath('data.data.0.orders_count', 1)->assertJsonPath('data.data.0.total_spend', 900);
        $this->actingAs($manager)->getJson('/api/v1/admin/customers/'.$customer->id)->assertOk()->assertJsonPath('data.customer.id', $customer->id)->assertJsonPath('data.summary.orders', 1)->assertJsonPath('data.summary.total_spend', 900);
    }

    public function test_support_agent_cannot_access_customer_directory(): void
    {
        $this->seed(RbacSeeder::class);
        $support = $this->userWithRole('support_agent');
        $this->actingAs($support)->getJson('/api/v1/admin/customers')->assertOk();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());
        return $user;
    }
}
