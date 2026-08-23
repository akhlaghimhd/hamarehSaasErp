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

Route::prefix('accounting')
    ->middleware(['auth:sanctum', 'tenant.context'])
    ->group(function () {

        // Fiscal Periods
        Route::post('/fiscal-periods', [FiscalPeriodController::class, 'store'])
            ->middleware('permission:accounting.fiscal-period.create');

        // Chart of Accounts
        Route::post('/accounts', [AccountController::class, 'store'])
            ->middleware('permission:accounting.account.create');

        // Vouchers (Headers)
        Route::post('/vouchers', [FinancialVoucherController::class, 'store'])
            ->middleware('permission:accounting.voucher.create');
        
        // Voucher Items (Lines)
        Route::post('/voucher-items', [FinancialVoucherItemController::class, 'store'])
            ->middleware('permission:accounting.voucher-item.create');

        // Tax Transactions
        Route::post('/tax-transactions', [TaxTransactionController::class, 'store'])
            ->middleware('permission:accounting.tax-transaction.create');
    });