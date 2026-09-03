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
| Do NOT add another prefix('accounting') here — that would produce
| /api/accounting/accounting/* (404 for clients/tests using /api/accounting/*).
| Pattern matches MasterData Routes.
*/

Route::middleware(['auth:sanctum', 'tenant.context', 'load.scopes'])->group(function () {

    // Fiscal Periods
    Route::post('/fiscal-periods', [FiscalPeriodController::class, 'store'])
        ->middleware('permission:accounting.fiscal-period.create');

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
