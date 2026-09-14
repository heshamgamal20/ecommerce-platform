<?php
namespace Tests\Feature;

use Tests\TestCase;

final class AdminAbandonedCartApiTest extends TestCase
{
    public function test_abandoned_carts_endpoint_is_registered(): void
    {
        $this->assertTrue(collect(app('router')->getRoutes()->getRoutes())
            ->contains(fn ($route) => $route->uri() === 'api/v1/admin/abandoned-carts' && in_array('GET', $route->methods(), true)));
    }
}
