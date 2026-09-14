<?php
namespace Tests\Feature;
use Tests\TestCase;
final class InvoiceApiTest extends TestCase
{
    public function test_invoice_routes_are_registered(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        $this->assertTrue($routes->contains(fn ($route) => $route->uri() === 'api/v1/admin/orders/{orderId}/invoice' && in_array('POST', $route->methods(), true)));
        $this->assertTrue($routes->contains(fn ($route) => $route->uri() === 'api/v1/admin/invoices/{invoiceId}/credit-notes' && in_array('POST', $route->methods(), true)));
    }

    public function test_every_invoice_route_requires_authentication(): void
    {
        $invoiceRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->getName() ?? '', 'admin.invoices.'));

        $this->assertCount(5, $invoiceRoutes);
        $invoiceRoutes->each(fn ($route) => $this->assertContains('auth', $route->middleware()));
    }
}
