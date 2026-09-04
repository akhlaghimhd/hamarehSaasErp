<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Inventory\Controllers\ItemController;
use App\Modules\Inventory\Controllers\WarehouseController;

/*
|--------------------------------------------------------------------------
| Inventory Module API Routes
|--------------------------------------------------------------------------
| Prefix پیش‌فرض توسط ModuleServiceProvider: /api/inventory
*/

Route::middleware(['auth:sanctum', 'tenant.context', 'load.scopes'])->group(function () {

    // Items (Single Source of Truth now in Inventory)
    Route::get('items', [ItemController::class, 'index'])
        ->middleware('permission:master-data.item.view');
    Route::post('items', [ItemController::class, 'store'])
        ->middleware('permission:master-data.item.create');
    Route::get('items/{id}', [ItemController::class, 'show'])
        ->middleware('permission:master-data.item.view');
    Route::put('items/{id}', [ItemController::class, 'update'])
        ->middleware('permission:master-data.item.update');
    Route::delete('items/{id}', [ItemController::class, 'destroy'])
        ->middleware('permission:master-data.item.delete');

    // Warehouses (Single Source of Truth now in Inventory)
    Route::get('warehouses', [WarehouseController::class, 'index'])
        ->middleware('permission:master-data.warehouse.view');
    Route::post('warehouses', [WarehouseController::class, 'store'])
        ->middleware('permission:master-data.warehouse.create');
    Route::get('warehouses/{id}', [WarehouseController::class, 'show'])
        ->middleware('permission:master-data.warehouse.view');
    Route::put('warehouses/{id}', [WarehouseController::class, 'update'])
        ->middleware('permission:master-data.warehouse.update');
    Route::delete('warehouses/{id}', [WarehouseController::class, 'destroy'])
        ->middleware('permission:master-data.warehouse.delete');

});
