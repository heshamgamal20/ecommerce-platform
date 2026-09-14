<?php

use App\Modules\Reports\Presentation\Http\Controllers\ReportsController;
use App\Modules\Reports\Presentation\Http\Controllers\ReportExportController;
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
    Route::get('reports/taxes', [ReportsController::class, 'taxes'])->name('reports.taxes');
    Route::get('reports/cashflow', [ReportsController::class, 'cashflow'])->name('reports.cashflow');
    Route::get('reports/payment-exceptions', [ReportsController::class, 'paymentExceptions'])->name('reports.payment-exceptions');
    Route::get('reports/operations', [ReportsController::class, 'operations'])->name('reports.operations');
    Route::get('reports/profitability', [ReportsController::class, 'profitability'])->name('reports.profitability');
    Route::get('reports/exports/sales.csv', [ReportExportController::class, 'sales'])->name('reports.exports.sales');
    Route::get('reports/exports/payments.csv', [ReportExportController::class, 'payments'])->name('reports.exports.payments');
    Route::get('reports/exports/customers.csv', [ReportExportController::class, 'customers'])->name('reports.exports.customers');
    Route::get('reports/exports/products.csv', [ReportExportController::class, 'products'])->name('reports.exports.products');
    Route::get('reports/exports/inventory.csv', [ReportExportController::class, 'inventory'])->name('reports.exports.inventory');
    Route::get('reports/exports/returns.csv', [ReportExportController::class, 'returns'])->name('reports.exports.returns');
    Route::get('reports/exports/shipments.csv', [ReportExportController::class, 'shipments'])->name('reports.exports.shipments');
    Route::get('reports/exports/settlements.csv', [ReportExportController::class, 'settlements'])->name('reports.exports.settlements');
    Route::get('reports/exports/audit-logs.csv', [ReportExportController::class, 'audit'])->name('reports.exports.audit');
});
