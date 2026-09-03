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
| ModuleServiceProvider already prefixes with api/accounting (from module name).
| Do NOT add another prefix('accounting') here.
*/

Route::middleware(['auth:sanctum', 'tenant.context', 'load.scopes'])->group(function () {

    // Fiscal Periods — full CRUD + close (L6-ACC-02.2)
    Route::get('/fiscal-periods', [FiscalPeriodController::class, 'index'])
        ->middleware('permission:accounting.fiscal-period.view');
    Route::post('/fiscal-periods', [FiscalPeriodController::class, 'store'])
        ->middleware('permission:accounting.fiscal-period.create');
    Route::get('/fiscal-periods/{id}', [FiscalPeriodController::class, 'show'])
        ->middleware('permission:accounting.fiscal-period.view');
    Route::put('/fiscal-periods/{id}', [FiscalPeriodController::class, 'update'])
        ->middleware('permission:accounting.fiscal-period.update');
    Route::post('/fiscal-periods/{id}/close', [FiscalPeriodController::class, 'close'])
        ->middleware('permission:accounting.fiscal-period.close');
    Route::delete('/fiscal-periods/{id}', [FiscalPeriodController::class, 'destroy'])
        ->middleware('permission:accounting.fiscal-period.delete');

    // Chart of Accounts — full CRUD (L6-ACC-02.1)
    Route::get('/accounts', [AccountController::class, 'index'])
        ->middleware('permission:accounting.account.view');
    Route::post('/accounts', [AccountController::class, 'store'])
        ->middleware('permission:accounting.account.create');
    Route::get('/accounts/{id}', [AccountController::class, 'show'])
        ->middleware('permission:accounting.account.view');
    Route::put('/accounts/{id}', [AccountController::class, 'update'])
        ->middleware('permission:accounting.account.update');
    Route::delete('/accounts/{id}', [AccountController::class, 'destroy'])
        ->middleware('permission:accounting.account.delete');

    // Vouchers — full CRUD + post (L6-ACC-02.3)
    Route::get('/vouchers', [FinancialVoucherController::class, 'index'])
        ->middleware('permission:accounting.voucher.view');
    Route::post('/vouchers', [FinancialVoucherController::class, 'store'])
        ->middleware('permission:accounting.voucher.create');
    Route::get('/vouchers/{id}', [FinancialVoucherController::class, 'show'])
        ->middleware('permission:accounting.voucher.view');
    Route::put('/vouchers/{id}', [FinancialVoucherController::class, 'update'])
        ->middleware('permission:accounting.voucher.update');
    Route::post('/vouchers/{id}/post', [FinancialVoucherController::class, 'post'])
        ->middleware('permission:accounting.voucher.post');
    Route::delete('/vouchers/{id}', [FinancialVoucherController::class, 'destroy'])
        ->middleware('permission:accounting.voucher.delete');

    // Voucher Items (Lines)
    Route::post('/voucher-items', [FinancialVoucherItemController::class, 'store'])
        ->middleware('permission:accounting.voucher-item.create');

    // Tax Transactions
    Route::post('/tax-transactions', [TaxTransactionController::class, 'store'])
        ->middleware('permission:accounting.tax-transaction.create');
});
