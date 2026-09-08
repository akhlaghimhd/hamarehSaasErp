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
use App\Modules\ProcurementSales\Controllers\PaymentSettlementController;
use App\Modules\ProcurementSales\Controllers\PurchaseRequisitionController;

Route::middleware(['auth:sanctum', 'tenant.context', 'load.scopes'])->group(function () {

    Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])
        ->middleware('permission:procurement.purchase-order.create');

    Route::post('purchase-receipts', [PurchaseReceiptController::class, 'store'])
        ->middleware('permission:procurement.purchase-receipt.create');
    Route::get('purchase-receipts/{id}', [PurchaseReceiptController::class, 'show'])
        ->middleware('permission:procurement.purchase-receipt.view');
    Route::post('purchase-receipts/{id}/post', [PurchaseReceiptController::class, 'post'])
        ->middleware('permission:procurement.purchase-receipt.post');

    Route::post('sales-quotations', [SalesQuotationController::class, 'store'])
        ->middleware('permission:procurement.sales-quotation.create');

    Route::post('sales-orders', [SalesOrderController::class, 'store'])
        ->middleware('permission:procurement.sales-order.create');
    Route::get('sales-orders/{id}', [SalesOrderController::class, 'show'])
        ->middleware('permission:procurement.sales-order.view');
    Route::post('sales-orders/{id}/confirm', [SalesOrderController::class, 'confirm'])
        ->middleware('permission:procurement.sales-order.confirm');

    Route::post('sales-deliveries', [SalesDeliveryOrderController::class, 'store'])
        ->middleware('permission:procurement.sales-delivery.create');
    Route::get('sales-deliveries/{id}', [SalesDeliveryOrderController::class, 'show'])
        ->middleware('permission:procurement.sales-delivery.view');
    Route::post('sales-deliveries/{id}/post', [SalesDeliveryOrderController::class, 'post'])
        ->middleware('permission:procurement.sales-delivery.post');

    Route::post('sales-invoices', [SalesInvoiceController::class, 'store'])
        ->middleware('permission:procurement.sales-invoice.create');
    Route::get('sales-invoices/{id}', [SalesInvoiceController::class, 'show'])
        ->middleware('permission:procurement.sales-invoice.view');
    Route::post('sales-invoices/{id}/post', [SalesInvoiceController::class, 'post'])
        ->middleware('permission:procurement.sales-invoice.post');
    Route::post('sales-invoices/{id}/submit-for-approval', [SalesInvoiceController::class, 'submitForApproval'])
        ->middleware('permission:procurement.sales-invoice.update');

    Route::post('purchase-invoices', [PurchaseInvoiceController::class, 'store'])
        ->middleware('permission:procurement.purchase-invoice.create');
    Route::get('purchase-invoices/{id}', [PurchaseInvoiceController::class, 'show'])
        ->middleware('permission:procurement.purchase-invoice.view');
    Route::post('purchase-invoices/{id}/post', [PurchaseInvoiceController::class, 'post'])
        ->middleware('permission:procurement.purchase-invoice.post');

    Route::post('purchase-requisitions', [PurchaseRequisitionController::class, 'store'])
        ->middleware('permission:procurement.purchase-requisition.create');
    Route::get('purchase-requisitions/{id}', [PurchaseRequisitionController::class, 'show'])
        ->middleware('permission:procurement.purchase-requisition.view');
    Route::post('purchase-requisitions/{id}/submit', [PurchaseRequisitionController::class, 'submit'])
        ->middleware('permission:procurement.purchase-requisition.submit');
    Route::post('purchase-requisitions/{id}/approve', [PurchaseRequisitionController::class, 'approve'])
        ->middleware('permission:procurement.purchase-requisition.approve');
    Route::post('purchase-requisitions/{id}/reject', [PurchaseRequisitionController::class, 'reject'])
        ->middleware('permission:procurement.purchase-requisition.approve');
    Route::post('purchase-requisitions/{id}/convert-to-po', [PurchaseRequisitionController::class, 'convertToPurchaseOrder'])
        ->middleware('permission:procurement.purchase-order.create');

    Route::get('payment-schedules/{paymentScheduleId}', [PaymentSettlementController::class, 'showSchedule'])
        ->middleware('permission:procurement.payment-schedule.view');
    Route::get('payment-schedules', [PaymentSettlementController::class, 'listForDocument'])
        ->middleware('permission:procurement.payment-schedule.view');
    Route::post('cash-transactions/receipts', [PaymentSettlementController::class, 'recordReceipt'])
        ->middleware('permission:procurement.cash-transaction.create');
    Route::post('cash-transactions/payments', [PaymentSettlementController::class, 'recordPayment'])
        ->middleware('permission:procurement.cash-transaction.create');

    Route::post('returns', [ReturnOrderController::class, 'store'])
        ->middleware('permission:procurement.return-order.create');
});
