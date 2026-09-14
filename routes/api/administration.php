<?php

use App\Modules\Administration\Presentation\Http\Controllers\AdminAuditController;
use App\Modules\Administration\Presentation\Http\Controllers\AdminNotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('admin/audit-logs', [AdminAuditController::class, 'index'])->name('admin.audit-logs.index');
    Route::get('admin/notifications', [AdminNotificationController::class, 'index'])->name('admin.notifications.index');
    Route::patch('admin/notifications/{notification}/read', [AdminNotificationController::class, 'read'])->name('admin.notifications.read');
    Route::patch('admin/notifications/read-all', [AdminNotificationController::class, 'readAll'])->name('admin.notifications.read-all');
});
