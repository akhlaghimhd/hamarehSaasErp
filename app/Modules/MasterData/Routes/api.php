<?php

use Illuminate\Support\Facades\Route;
use App\Modules\MasterData\Controllers\BusinessPartnerController;
use App\Modules\MasterData\Controllers\ItemController;
use App\Modules\MasterData\Controllers\CostCenterController;
use App\Modules\MasterData\Controllers\WarehouseController;

// حذف کامل Route::prefix چون ModuleServiceProvider به صورت خودکار api/master-data را اضافه می‌کند
Route::middleware(['auth:api', 'tenant.context'])->group(function () {
    
    // Business Partners (مشتریان، تامین‌کنندگان و پیمانکاران)
    Route::apiResource('business-partners', BusinessPartnerController::class);

    // Items (کالاها، مواد اولیه و خدمات)
    Route::apiResource('items', ItemController::class);

    // Cost Centers (ساختار سازمانی و مراکز هزینه)
    Route::apiResource('cost-centers', CostCenterController::class);

    // Warehouses (انبارها)
    Route::apiResource('warehouses', WarehouseController::class);

});