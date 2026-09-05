<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Inventory\Controllers\ItemController;
use App\Modules\Inventory\Controllers\WarehouseController;
use App\Modules\Inventory\Controllers\LocationController;
use App\Modules\Inventory\Controllers\InventoryDocumentController;
use App\Modules\Inventory\Controllers\InventoryDocumentItemController;
use App\Modules\Inventory\Controllers\StockBalanceController;
use App\Modules\Inventory\Controllers\StockReservationController;
use App\Modules\Inventory\Controllers\StockBatchController;
use App\Modules\Inventory\Controllers\ItemBarcodeController;

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

    // Stock batches (lot / expiry / QC)
    Route::get('stock-batches', [StockBatchController::class, 'index'])
        ->middleware('permission:inventory.stock-batch.view');
    Route::post('stock-batches', [StockBatchController::class, 'store'])
        ->middleware('permission:inventory.stock-batch.create');
    Route::get('stock-batches/{id}', [StockBatchController::class, 'show'])
        ->middleware('permission:inventory.stock-batch.view');
    Route::put('stock-batches/{id}', [StockBatchController::class, 'update'])
        ->middleware('permission:inventory.stock-batch.update');
    Route::delete('stock-batches/{id}', [StockBatchController::class, 'destroy'])
        ->middleware('permission:inventory.stock-batch.delete');

    // Item barcodes (L6-INV-17 — Owner: Inventory)
    Route::get('item-barcodes', [ItemBarcodeController::class, 'index'])
        ->middleware('permission:inventory.item-barcode.view');
    Route::post('item-barcodes', [ItemBarcodeController::class, 'store'])
        ->middleware('permission:inventory.item-barcode.create');
    Route::get('item-barcodes/{id}', [ItemBarcodeController::class, 'show'])
        ->middleware('permission:inventory.item-barcode.view');
    Route::put('item-barcodes/{id}', [ItemBarcodeController::class, 'update'])
        ->middleware('permission:inventory.item-barcode.update');
    Route::delete('item-barcodes/{id}', [ItemBarcodeController::class, 'destroy'])
        ->middleware('permission:inventory.item-barcode.delete');

    // Stock balances (read-only ledger; mutated via document post/void and reservation)
    Route::get('stock-balances', [StockBalanceController::class, 'index'])
        ->middleware('permission:inventory.stock-balance.view');
    Route::get('stock-balances/{id}', [StockBalanceController::class, 'show'])
        ->middleware('permission:inventory.stock-balance.view');

    // Soft stock reservation (L6-INV-13)
    Route::post('stock-reservations/reserve', [StockReservationController::class, 'reserve'])
        ->middleware('permission:inventory.stock-reservation.reserve');
    Route::post('stock-reservations/release', [StockReservationController::class, 'release'])
        ->middleware('permission:inventory.stock-reservation.release');

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
    Route::post('documents/{id}/void', [InventoryDocumentController::class, 'void'])
        ->middleware('permission:inventory.document.void');
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
