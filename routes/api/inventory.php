<?php

use App\Modules\Inventory\Presentation\Http\Controllers\InventoryController;
use App\Modules\Inventory\Presentation\Http\Controllers\AdminInventoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('admin/inventory', [AdminInventoryController::class, 'index'])->name('admin.inventory.index');
    Route::get('admin/inventory/{item}/movements', [AdminInventoryController::class, 'movements'])->name('admin.inventory.movements');
    Route::get('admin/inventory/{item}', [AdminInventoryController::class, 'show'])->name('admin.inventory.show');
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
    Route::post('inventory/reserve', [InventoryController::class, 'reserve'])->name('inventory.reserve');
    Route::post('inventory/release', [InventoryController::class, 'release'])->name('inventory.release');
});
