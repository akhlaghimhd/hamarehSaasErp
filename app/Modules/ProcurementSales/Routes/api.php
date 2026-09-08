<?php

use Illuminate\Support\Facades\Route;
use App\Modules\ProcurementSales\Controllers\PurchaseOrderController;
use App\Modules\ProcurementSales\Controllers\PurchaseReceiptController;
use App\Modules\ProcurementSales\Controllers\SalesOrderController;
use App\Modules\ProcurementSales\Controllers\SalesDeliveryOrderController;
use App\Modules\ProcurementSales\Controllers\SalesQuotationController;
use App\Modules\ProcurementSales\Controllers\ReturnOrderController;
use App\Modules\ProcurementSales\Controllers\SalesInvoiceController;
use App\Modules\ProcurementSales\Controllers\PurchaseInvoiceController;

/*
|--------------------------------------------------------------------------
| Procurement & Sales Module API Routes
|--------------------------------------------------------------------------
| Prefix applied by ModuleServiceProvider: /api/procurement-sales
*/

Route::middleware(['auth:sanctum', 'tenant.context', 'load.scopes'])->group(function () {

    // Purchase Orders
    Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])
        ->middleware('permission:procurement.purchase-order.create');

    // Purchase Receipts
    Route::post('purchase-receipts', [PurchaseReceiptController::class, 'store'])
        ->middleware('permission:procurement.purchase-receipt.create');
    Route::get('purchase-receipts/{id}', [PurchaseReceiptController::class, 'show'])
        ->middleware('permission:procurement.purchase-receipt.view');
    Route::post('purchase-receipts/{id}/post', [PurchaseReceiptController::class, 'post'])
        ->middleware('permission:procurement.purchase-receipt.post');

    // Sales Quotations
    Route::post('sales-quotations', [SalesQuotationController::class, 'store'])
        ->middleware('permission:procurement.sales-quotation.create');

    // Sales Orders
    Route::post('sales-orders', [SalesOrderController::class, 'store'])
        ->middleware('permission:procurement.sales-order.create');
    Route::get('sales-orders/{id}', [SalesOrderController::class, 'show'])
        ->middleware('permission:procurement.sales-order.view');
    Route::post('sales-orders/{id}/confirm', [SalesOrderController::class, 'confirm'])
        ->middleware('permission:procurement.sales-order.confirm');

    // Sales Deliveries
    Route::post('sales-deliveries', [SalesDeliveryOrderController::class, 'store'])
        ->middleware('permission:procurement.sales-delivery.create');
    Route::get('sales-deliveries/{id}', [SalesDeliveryOrderController::class, 'show'])
        ->middleware('permission:procurement.sales-delivery.view');
    Route::post('sales-deliveries/{id}/post', [SalesDeliveryOrderController::class, 'post'])
        ->middleware('permission:procurement.sales-delivery.post');

    // Sales Invoices (L6-PS-07)
    Route::post('sales-invoices', [SalesInvoiceController::class, 'store'])
        ->middleware('permission:procurement.sales-invoice.create');
    Route::get('sales-invoices/{id}', [SalesInvoiceController::class, 'show'])
        ->middleware('permission:procurement.sales-invoice.view');
    Route::post('sales-invoices/{id}/post', [SalesInvoiceController::class, 'post'])
        ->middleware('permission:procurement.sales-invoice.post');

    // Purchase Invoices (L6-PS-07)
    Route::post('purchase-invoices', [PurchaseInvoiceController::class, 'store'])
        ->middleware('permission:procurement.purchase-invoice.create');
    Route::get('purchase-invoices/{id}', [PurchaseInvoiceController::class, 'show'])
        ->middleware('permission:procurement.purchase-invoice.view');
    Route::post('purchase-invoices/{id}/post', [PurchaseInvoiceController::class, 'post'])
        ->middleware('permission:procurement.purchase-invoice.post');

    // Returns
    Route::post('returns', [ReturnOrderController::class, 'store'])
        ->middleware('permission:procurement.return-order.create');
});
