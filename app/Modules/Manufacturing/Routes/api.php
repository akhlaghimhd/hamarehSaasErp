<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Manufacturing\Controllers\WorkCenterController;
use App\Modules\Manufacturing\Controllers\BomController;
use App\Modules\Manufacturing\Controllers\ProductionOrderController;
use App\Modules\Manufacturing\Controllers\ProductionRoutingController;
use App\Modules\Manufacturing\Controllers\ProductionLogController;
use App\Modules\Manufacturing\Controllers\QualityInspectionController;

/*
|--------------------------------------------------------------------------
| Manufacturing Module API Routes
| Prefix by ModuleServiceProvider: /api/manufacturing
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'tenant.context', 'load.scopes'])->group(function () {

    Route::get('work-centers', [WorkCenterController::class, 'index'])
        ->middleware('permission:manufacturing.work-center.view');
    Route::post('work-centers', [WorkCenterController::class, 'store'])
        ->middleware('permission:manufacturing.work-center.create');
    Route::get('work-centers/{id}', [WorkCenterController::class, 'show'])
        ->middleware('permission:manufacturing.work-center.view');
    Route::put('work-centers/{id}', [WorkCenterController::class, 'update'])
        ->middleware('permission:manufacturing.work-center.update');
    Route::delete('work-centers/{id}', [WorkCenterController::class, 'destroy'])
        ->middleware('permission:manufacturing.work-center.delete');

    Route::get('boms', [BomController::class, 'index'])
        ->middleware('permission:manufacturing.bom.view');
    Route::post('boms', [BomController::class, 'store'])
        ->middleware('permission:manufacturing.bom.create');
    Route::get('boms/{id}', [BomController::class, 'show'])
        ->middleware('permission:manufacturing.bom.view');
    Route::post('boms/{id}/approve', [BomController::class, 'approve'])
        ->middleware('permission:manufacturing.bom.approve');
    Route::delete('boms/{id}', [BomController::class, 'destroy'])
        ->middleware('permission:manufacturing.bom.delete');

    Route::get('production-orders', [ProductionOrderController::class, 'index'])
        ->middleware('permission:manufacturing.production-order.view');
    Route::post('production-orders', [ProductionOrderController::class, 'store'])
        ->middleware('permission:manufacturing.production-order.create');
    Route::get('production-orders/{id}', [ProductionOrderController::class, 'show'])
        ->middleware('permission:manufacturing.production-order.view');
    Route::post('production-orders/{id}/release', [ProductionOrderController::class, 'release'])
        ->middleware('permission:manufacturing.production-order.release');
    Route::post('production-orders/{id}/issue-materials', [ProductionOrderController::class, 'issueMaterials'])
        ->middleware('permission:manufacturing.production-order.issue');
    Route::post('production-orders/{id}/complete', [ProductionOrderController::class, 'complete'])
        ->middleware('permission:manufacturing.production-order.complete');
    Route::get('production-orders/{id}/consumptions', [ProductionOrderController::class, 'consumptions'])
        ->middleware('permission:manufacturing.production-order.view');

    Route::get('production-orders/{productionOrderId}/routing', [ProductionRoutingController::class, 'index'])
        ->middleware('permission:manufacturing.routing.view');
    Route::post('production-orders/{productionOrderId}/routing', [ProductionRoutingController::class, 'store'])
        ->middleware('permission:manufacturing.routing.create');
    Route::post('routing/{routingId}/start', [ProductionRoutingController::class, 'start'])
        ->middleware('permission:manufacturing.routing.update');
    Route::post('routing/{routingId}/complete', [ProductionRoutingController::class, 'complete'])
        ->middleware('permission:manufacturing.routing.update');

    Route::get('production-orders/{productionOrderId}/logs', [ProductionLogController::class, 'index'])
        ->middleware('permission:manufacturing.log.view');
    Route::post('production-orders/{productionOrderId}/logs', [ProductionLogController::class, 'store'])
        ->middleware('permission:manufacturing.log.create');

    Route::get('quality-inspections', [QualityInspectionController::class, 'index'])
        ->middleware('permission:manufacturing.qc.view');
    Route::post('quality-inspections', [QualityInspectionController::class, 'store'])
        ->middleware('permission:manufacturing.qc.create');
    Route::get('quality-inspections/{id}', [QualityInspectionController::class, 'show'])
        ->middleware('permission:manufacturing.qc.view');
    Route::post('quality-inspections/{id}/dispose', [QualityInspectionController::class, 'dispose'])
        ->middleware('permission:manufacturing.qc.dispose');

});
