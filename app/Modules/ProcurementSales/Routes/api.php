<?php

use Illuminate\Support\Facades\Route;
use App\Modules\ProcurementSales\Controllers\PurchaseOrderController;
use App\Modules\ProcurementSales\Controllers\PurchaseReceiptController;
use App\Modules\ProcurementSales\Controllers\SalesOrderController;
use App\Modules\ProcurementSales\Controllers\SalesDeliveryOrderController;
use App\Modules\ProcurementSales\Controllers\SalesQuotationController;
use App\Modules\ProcurementSales\Controllers\ReturnOrderController;

Route::prefix('api/procurement-sales')->middleware(['api', 'tenant.context'])->group(function () {
    
    // Procurement
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::post('/purchase-receipts', [PurchaseReceiptController::class, 'store']);
    
    // Sales
    Route::post('/sales-quotations', [SalesQuotationController::class, 'store']);
    Route::post('/sales-orders', [SalesOrderController::class, 'store']);
    Route::post('/sales-deliveries', [SalesDeliveryOrderController::class, 'store']);
    
    // Returns
    Route::post('/returns', [ReturnOrderController::class, 'store']);
});