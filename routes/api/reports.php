<?php

use App\Modules\Reports\Presentation\Http\Controllers\ReportsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('reports/sales', [ReportsController::class, 'sales'])->name('reports.sales');
    Route::get('reports/payments', [ReportsController::class, 'payments'])->name('reports.payments');
    Route::get('reports/returns', [ReportsController::class, 'returns'])->name('reports.returns');
    Route::get('reports/carriers/performance', [ReportsController::class, 'carrierPerformance'])->name('reports.carriers.performance');
    Route::get('reports/inventory', [ReportsController::class, 'inventory'])->name('reports.inventory');
    Route::get('reports/customers', [ReportsController::class, 'customers'])->name('reports.customers');
    Route::get('reports/products', [ReportsController::class, 'products'])->name('reports.products');
    Route::get('reports/coupons', [ReportsController::class, 'coupons'])->name('reports.coupons');
});
