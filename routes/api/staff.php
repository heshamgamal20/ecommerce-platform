<?php

use App\Modules\Staff\Presentation\Http\Controllers\StaffController;
use App\Modules\Staff\Presentation\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
    Route::match(['put', 'patch'], 'staff/{staffId}', [StaffController::class, 'update'])->name('staff.update');
    Route::delete('staff/{staffId}', [StaffController::class, 'destroy'])->name('staff.destroy');
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    Route::get('staff/{staffId}/effective-permissions', [RoleController::class, 'effectivePermissions'])->name('staff.permissions');
    Route::put('staff/{staffId}/permission-overrides', [RoleController::class, 'overrides'])->name('staff.permission-overrides');
    Route::post('staff/{staffId}/revoke-sessions', [RoleController::class, 'revokeSessions'])->name('staff.revoke-sessions');
});
