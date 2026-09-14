<?php

use App\Modules\Shipping\Presentation\Http\Controllers\ShippingController;
use App\Modules\Shipping\Presentation\Http\Controllers\AdminShipmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('admin/shipments/exceptions', [AdminShipmentController::class, 'exceptions'])->name('admin.shipments.exceptions');
    Route::get('admin/shipments', [AdminShipmentController::class, 'index'])->name('admin.shipments.index');
    Route::get('admin/shipments/{shipment}', [AdminShipmentController::class, 'show'])->name('admin.shipments.show');
    Route::get('shipping-methods', [ShippingController::class, 'index'])->name('shipping-methods.index');
    Route::get('shipping-methods/{shippingMethodId}', [ShippingController::class, 'show'])->name('shipping-methods.show');
    Route::post('shipping-methods', [ShippingController::class, 'store'])->name('shipping-methods.store');
    Route::match(['put', 'patch'], 'shipping-methods/{shippingMethodId}', [ShippingController::class, 'update'])->name('shipping-methods.update');
    Route::delete('shipping-methods/{shippingMethodId}', [ShippingController::class, 'destroy'])->name('shipping-methods.destroy');
    Route::patch('shipments/{shipmentId}/status', [ShippingController::class, 'status'])->name('shipments.status');
    Route::get('shipping-reports', [ShippingController::class, 'report'])->name('shipping-reports.index');
    Route::get('shipping-reconciliation', [ShippingController::class, 'reconciliation'])->name('shipping-reconciliation.index');
    Route::post('shipping-settlements', [ShippingController::class, 'settlements'])->name('shipping-settlements.store');
    Route::get('shipping-settlements', [ShippingController::class, 'settlementIndex'])->name('shipping-settlements.index');
});
