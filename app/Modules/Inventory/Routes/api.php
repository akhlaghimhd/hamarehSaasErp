<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Inventory\Controllers\ItemController;
use App\Modules\Inventory\Controllers\WarehouseController;
use App\Modules\Inventory\Controllers\LocationController;
use App\Modules\Inventory\Controllers\InventoryDocumentController;
use App\Modules\Inventory\Controllers\InventoryDocumentItemController;

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

    // Locations (bins / shelves under a warehouse)
    Route::get('locations', [LocationController::class, 'index'])
        ->middleware('permission:inventory.location.view');
    Route::post('locations', [LocationController::class, 'store'])
        ->middleware('permission:inventory.location.create');
    Route::get('locations/{id}', [LocationController::class, 'show'])
        ->middleware('permission:inventory.location.view');
    Route::put('locations/{id}', [LocationController::class, 'update'])
        ->middleware('permission:inventory.location.update');
    Route::delete('locations/{id}', [LocationController::class, 'destroy'])
        ->middleware('permission:inventory.location.delete');

    // Inventory Documents (Receipt / Issue / Transfer / Adjustment headers)
    Route::get('documents', [InventoryDocumentController::class, 'index'])
        ->middleware('permission:inventory.document.view');
    Route::post('documents', [InventoryDocumentController::class, 'store'])
        ->middleware('permission:inventory.document.create');
    Route::get('documents/{id}', [InventoryDocumentController::class, 'show'])
        ->middleware('permission:inventory.document.view');
    Route::put('documents/{id}', [InventoryDocumentController::class, 'update'])
        ->middleware('permission:inventory.document.update');
    Route::post('documents/{id}/post', [InventoryDocumentController::class, 'post'])
        ->middleware('permission:inventory.document.post');
    Route::delete('documents/{id}', [InventoryDocumentController::class, 'destroy'])
        ->middleware('permission:inventory.document.delete');

    // Document line items (draft document only for mutations)
    Route::get('document-items', [InventoryDocumentItemController::class, 'index'])
        ->middleware('permission:inventory.document-item.view');
    Route::post('document-items', [InventoryDocumentItemController::class, 'store'])
        ->middleware('permission:inventory.document-item.create');
    Route::get('document-items/{id}', [InventoryDocumentItemController::class, 'show'])
        ->middleware('permission:inventory.document-item.view');
    Route::put('document-items/{id}', [InventoryDocumentItemController::class, 'update'])
        ->middleware('permission:inventory.document-item.update');
    Route::delete('document-items/{id}', [InventoryDocumentItemController::class, 'destroy'])
        ->middleware('permission:inventory.document-item.delete');

});
