<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PurchasePriceSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_owner_can_set_read_and_view_purchase_price(): void
    {
        $this->seed(RbacSeeder::class);
        $owner = $this->userWithRole('owner');
        $manager = $this->userWithRole('manager');
        $payload = ['name' => 'Protected Cost Product', 'slug' => 'protected-cost-product', 'type' => 'simple', 'status' => 'active', 'purchase_price' => 300];

        $this->actingAs($manager)->postJson('/api/v1/products', $payload)->assertUnprocessable();
        $product = $this->actingAs($owner)->postJson('/api/v1/products', $payload)->assertCreated()->assertJsonMissingPath('data.purchase_price')->json('data');
        $this->getJson('/api/v1/products/'.$product['id'])->assertOk()->assertJsonMissingPath('data.purchase_price');
        $this->actingAs($manager)->getJson('/api/v1/products/'.$product['id'].'/purchase-price')->assertForbidden();
        $this->actingAs($owner)->getJson('/api/v1/products/'.$product['id'].'/purchase-price')->assertOk()->assertJsonPath('data.purchase_price', 300);
        $this->actingAs($manager)->getJson('/api/v1/reports/profitability?from=2026-01-01&to=2027-01-01')->assertForbidden();
        $this->actingAs($owner)->getJson('/api/v1/reports/profitability?from=2026-01-01&to=2027-01-01')->assertOk()->assertJsonPath('data.complete', true);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());
        return $user;
    }
}
