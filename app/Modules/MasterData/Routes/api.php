<?php

use Illuminate\Support\Facades\Route;
use App\Modules\MasterData\Controllers\BusinessPartnerController;
use App\Modules\MasterData\Controllers\ItemController;
use App\Modules\MasterData\Controllers\CostCenterController;
use App\Modules\MasterData\Controllers\WarehouseController;

/*
|--------------------------------------------------------------------------
| Master Data API Routes
|--------------------------------------------------------------------------
| Prefix پیش‌فرض توسط ModuleServiceProvider: /api/master-data
*/

Route::middleware(['auth:sanctum', 'tenant.context', 'load.scopes'])->group(function () {

    // Business Partners
    Route::get('business-partners', [BusinessPartnerController::class, 'index'])
        ->middleware('permission:master-data.business-partner.view');
    Route::post('business-partners', [BusinessPartnerController::class, 'store'])
        ->middleware('permission:master-data.business-partner.create');
    Route::get('business-partners/{id}', [BusinessPartnerController::class, 'show'])
        ->middleware('permission:master-data.business-partner.view');
    Route::put('business-partners/{id}', [BusinessPartnerController::class, 'update'])
        ->middleware('permission:master-data.business-partner.update');
    Route::delete('business-partners/{id}', [BusinessPartnerController::class, 'destroy'])
        ->middleware('permission:master-data.business-partner.delete');

    // Items
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

    // Cost Centers
    Route::get('cost-centers', [CostCenterController::class, 'index'])
        ->middleware('permission:master-data.cost-center.view');
    Route::post('cost-centers', [CostCenterController::class, 'store'])
        ->middleware('permission:master-data.cost-center.create');
    Route::get('cost-centers/{id}', [CostCenterController::class, 'show'])
        ->middleware('permission:master-data.cost-center.view');
    Route::put('cost-centers/{id}', [CostCenterController::class, 'update'])
        ->middleware('permission:master-data.cost-center.update');
    Route::delete('cost-centers/{id}', [CostCenterController::class, 'destroy'])
        ->middleware('permission:master-data.cost-center.delete');

    // Warehouses
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