<?php

use App\Modules\Payment\Presentation\Http\Controllers\PaymentController;
use App\Modules\Payment\Presentation\Http\Controllers\OperationalDashboardController;
use App\Modules\Payment\Presentation\Http\Controllers\AdminPaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('admin/payments/exceptions', [AdminPaymentController::class, 'exceptions'])->name('admin.payments.exceptions');
    Route::get('admin/payments', [AdminPaymentController::class, 'index'])->name('admin.payments.index');
    Route::get('admin/payments/{payment}', [AdminPaymentController::class, 'show'])->name('admin.payments.show');
    Route::get('orders/{orderId}/payments', [PaymentController::class, 'adminIndex'])->name('payments.index');
    Route::get('operations/dashboard', OperationalDashboardController::class)->name('operations.dashboard');
    Route::post('payments/{paymentId}/confirm', [PaymentController::class, 'confirm'])->name('payments.confirm');
    Route::post('payments/{paymentId}/refund', [PaymentController::class, 'refund'])->name('payments.refund');
});
