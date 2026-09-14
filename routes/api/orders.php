<?php

use App\Modules\Order\Presentation\Http\Controllers\OrderController;
use App\Modules\Order\Presentation\Http\Controllers\AdminOrderController;
use App\Modules\Order\Presentation\Http\Controllers\ReturnController;
use App\Modules\Order\Presentation\Http\Controllers\AdminReturnController;
use App\Modules\Order\Presentation\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('admin/returns', [AdminReturnController::class, 'index'])->name('admin.returns.index');
    Route::get('admin/returns/{return}', [AdminReturnController::class, 'show'])->name('admin.returns.show');
    Route::post('admin/returns/{return}/receive', [AdminReturnController::class, 'receive'])->name('admin.returns.receive');
    Route::post('admin/returns/{return}/inspect', [AdminReturnController::class, 'inspect'])->name('admin.returns.inspect');
    Route::get('admin/orders', [AdminOrderController::class, 'index'])->name('admin.orders.index');
    Route::get('admin/orders/{order}', [AdminOrderController::class, 'show'])->name('admin.orders.show');
    Route::get('admin/orders/{orderId}/invoice', [InvoiceController::class, 'show'])->name('admin.invoices.show');
    Route::post('admin/orders/{orderId}/invoice', [InvoiceController::class, 'issue'])->name('admin.invoices.issue');
    Route::get('admin/orders/{orderId}/invoice/print', [InvoiceController::class, 'print'])->name('admin.invoices.print');
    Route::patch('admin/invoices/{invoiceId}/cancel', [InvoiceController::class, 'cancel'])->name('admin.invoices.cancel');
    Route::post('admin/invoices/{invoiceId}/credit-notes', [InvoiceController::class, 'creditNote'])->name('admin.invoices.credit-notes.store');
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{orderId}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{orderId}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
    Route::post('orders/{orderId}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::get('returns', [ReturnController::class, 'index'])->name('returns.index');
    Route::patch('returns/{returnId}/approve', [ReturnController::class, 'approve'])->name('returns.approve');
    Route::patch('returns/{returnId}/reject', [ReturnController::class, 'reject'])->name('returns.reject');
});
