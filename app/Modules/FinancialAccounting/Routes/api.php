<?php

declare(strict_types=1);

use App\Modules\FinancialAccounting\Controllers\AccountController;
use App\Modules\FinancialAccounting\Controllers\BankReconciliationController;
use App\Modules\FinancialAccounting\Controllers\ChequeController;
use App\Modules\FinancialAccounting\Controllers\JournalEntryController;
use App\Modules\FinancialAccounting\Controllers\OpenItemController;
use App\Modules\FinancialAccounting\Controllers\PeriodControlController;
use App\Modules\FinancialAccounting\Controllers\ReportController;
use App\Modules\FinancialAccounting\Controllers\TreasuryController;
use Illuminate\Support\Facades\Route;

/**
 * Loaded as /api/v1/financial-accounting/...
 */
Route::middleware([
    'auth:sanctum',
    'tenant.context',
    'load.scopes',
])->group(function () {

    Route::get('/accounts', [AccountController::class, 'index'])
        ->middleware('permission:finance.coa.view');
    Route::post('/accounts', [AccountController::class, 'store'])
        ->middleware('permission:finance.coa.create');
    Route::get('/accounts/{account}', [AccountController::class, 'show'])
        ->middleware('permission:finance.coa.view');
    Route::put('/accounts/{account}', [AccountController::class, 'update'])
        ->middleware('permission:finance.coa.update');
    Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])
        ->middleware('permission:finance.coa.delete');

    Route::get('/journals', [JournalEntryController::class, 'index'])
        ->middleware('permission:finance.journal.view');
    Route::post('/journals', [JournalEntryController::class, 'store'])
        ->middleware('permission:finance.journal.create');
    Route::get('/journals/{journal}', [JournalEntryController::class, 'show'])
        ->middleware('permission:finance.journal.view');
    Route::put('/journals/{journal}', [JournalEntryController::class, 'update'])
        ->middleware('permission:finance.journal.update');
    Route::delete('/journals/{journal}', [JournalEntryController::class, 'destroy'])
        ->middleware('permission:finance.journal.delete');
    Route::post('/journals/{journal}/post', [JournalEntryController::class, 'post'])
        ->middleware('permission:finance.journal.post');
    Route::post('/journals/{journal}/reverse', [JournalEntryController::class, 'reverse'])
        ->middleware('permission:finance.journal.reverse');

    Route::get('/period-controls', [PeriodControlController::class, 'show'])
        ->middleware('permission:finance.period.view');
    Route::post('/period-controls/soft-close', [PeriodControlController::class, 'softClose'])
        ->middleware('permission:finance.period.close');
    Route::post('/period-controls/hard-close', [PeriodControlController::class, 'hardClose'])
        ->middleware('permission:finance.period.close');
    Route::post('/period-controls/reopen', [PeriodControlController::class, 'reopen'])
        ->middleware('permission:finance.period.reopen');

    Route::get('/reports/trial-balance', [ReportController::class, 'trialBalance'])
        ->middleware('permission:finance.report.view');
    Route::get('/reports/profit-and-loss', [ReportController::class, 'profitAndLoss'])
        ->middleware('permission:finance.report.view');
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])
        ->middleware('permission:finance.report.view');

    // FIN-P1 treasury / AR / AP / cheque / recon
    Route::get('/cash-accounts', [TreasuryController::class, 'cashAccounts'])
        ->middleware('permission:finance.treasury.view');
    Route::post('/cash-accounts', [TreasuryController::class, 'storeCashAccount'])
        ->middleware('permission:finance.treasury.manage');
    Route::get('/treasury-documents', [TreasuryController::class, 'documents'])
        ->middleware('permission:finance.treasury.view');
    Route::post('/treasury-documents', [TreasuryController::class, 'storeDocument'])
        ->middleware('permission:finance.treasury.manage');
    Route::post('/treasury-documents/{document}/post', [TreasuryController::class, 'post'])
        ->middleware('permission:finance.treasury.post');

    Route::get('/cheques', [ChequeController::class, 'index'])
        ->middleware('permission:finance.treasury.view');
    Route::post('/cheques', [ChequeController::class, 'store'])
        ->middleware('permission:finance.treasury.manage');
    Route::post('/cheques/{cheque}/transition', [ChequeController::class, 'transition'])
        ->middleware('permission:finance.treasury.manage');

    Route::get('/open-items', [OpenItemController::class, 'index'])
        ->middleware('permission:finance.ar.view');
    Route::post('/open-items', [OpenItemController::class, 'store'])
        ->middleware('permission:finance.ar.manage');
    Route::post('/open-items/{openItem}/allocate', [OpenItemController::class, 'allocate'])
        ->middleware('permission:finance.ar.manage');
    Route::get('/open-items/aging', [OpenItemController::class, 'aging'])
        ->middleware('permission:finance.ar.view');

    Route::get('/bank-statements', [BankReconciliationController::class, 'index'])
        ->middleware('permission:finance.treasury.view');
    Route::post('/bank-statements', [BankReconciliationController::class, 'store'])
        ->middleware('permission:finance.treasury.manage');
    Route::post('/bank-statement-lines/{line}/match', [BankReconciliationController::class, 'matchLine'])
        ->middleware('permission:finance.treasury.manage');
});
