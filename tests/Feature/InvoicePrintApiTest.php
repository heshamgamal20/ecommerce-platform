<?php
namespace Tests\Feature;
use Tests\TestCase;
final class InvoicePrintApiTest extends TestCase
{
    public function test_invoice_print_route_is_registered(): void
    {
        $this->assertTrue(collect(app('router')->getRoutes()->getRoutes())->contains(fn ($route) => $route->uri() === 'api/v1/admin/orders/{orderId}/invoice/print' && in_array('GET', $route->methods(), true)));
    }
}
