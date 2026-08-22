<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Accounting\Controllers\FinancialVoucherController;
use App\Modules\Accounting\Controllers\FinancialVoucherItemController;
use App\Modules\Accounting\Controllers\AccountController;
use App\Modules\Accounting\Controllers\FiscalPeriodController;
use App\Modules\Accounting\Controllers\TaxTransactionController;

/*
|--------------------------------------------------------------------------
| Accounting Module Routes
|--------------------------------------------------------------------------
*/

Route::prefix('accounting')->middleware(['auth:api', 'tenant.context'])->group(function () {
    // Fiscal Periods
    Route::post('/fiscal-periods', [FiscalPeriodController::class, 'store']);

    // Chart of Accounts
    Route::post('/accounts', [AccountController::class, 'store']);

    // Vouchers (Headers)
    Route::post('/vouchers', [FinancialVoucherController::class, 'store']);
    
    // Voucher Items (Lines)
    Route::post('/voucher-items', [FinancialVoucherItemController::class, 'store']);

    // Tax Transactions
    Route::post('/tax-transactions', [TaxTransactionController::class, 'store']);
});