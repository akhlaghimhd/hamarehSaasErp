<?php

declare(strict_types=1);

use App\Modules\FinancialAccounting\Controllers\AccountController;
use App\Modules\FinancialAccounting\Controllers\BankReconciliationController;
use App\Modules\FinancialAccounting\Controllers\ChequeController;
use App\Modules\FinancialAccounting\Controllers\ComplianceAlertController;
use App\Modules\FinancialAccounting\Controllers\FixedAssetController;
use App\Modules\FinancialAccounting\Controllers\IntercompanyController;
use App\Modules\FinancialAccounting\Controllers\JournalEntryController;
use App\Modules\FinancialAccounting\Controllers\MoodianController;
use App\Modules\FinancialAccounting\Controllers\OpenItemController;
use App\Modules\FinancialAccounting\Controllers\PeriodCloseController;
use App\Modules\FinancialAccounting\Controllers\PeriodControlController;
use App\Modules\FinancialAccounting\Controllers\ReportController;
use App\Modules\FinancialAccounting\Controllers\SmartAssistController;
use App\Modules\FinancialAccounting\Controllers\SuggestedJournalController;
use App\Modules\FinancialAccounting\Controllers\TaxController;
use App\Modules\FinancialAccounting\Controllers\TreasuryController;
use Illuminate\Support\Facades\Route;

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
    Route::post('/journals/{journal}/anomaly-scan', [SmartAssistController::class, 'anomalyScan'])
        ->middleware('permission:finance.smart.view');

    Route::get('/period-controls', [PeriodControlController::class, 'show'])
        ->middleware('permission:finance.period.view');
    Route::post('/period-controls/soft-close', [PeriodControlController::class, 'softClose'])
        ->middleware('permission:finance.period.close');
    Route::post('/period-controls/hard-close', [PeriodControlController::class, 'hardClose'])
        ->middleware('permission:finance.period.close');
    Route::post('/period-controls/reopen', [PeriodControlController::class, 'reopen'])
        ->middleware('permission:finance.period.reopen');

    Route::post('/period-close/evaluate', [PeriodCloseController::class, 'evaluate'])
        ->middleware('permission:finance.period.view');
    Route::post('/period-close/soft-close', [PeriodCloseController::class, 'softClose'])
        ->middleware('permission:finance.period.close');
    Route::post('/period-close/hard-close', [PeriodCloseController::class, 'hardClose'])
        ->middleware('permission:finance.period.close');

    Route::get('/reports/trial-balance', [ReportController::class, 'trialBalance'])
        ->middleware('permission:finance.report.view');
    Route::get('/reports/profit-and-loss', [ReportController::class, 'profitAndLoss'])
        ->middleware('permission:finance.report.view');
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])
        ->middleware('permission:finance.report.view');
    Route::get('/reports/consolidated-trial-balance', [IntercompanyController::class, 'consolidatedTrialBalance'])
        ->middleware('permission:finance.ic.view');
    Route::get('/reports/pl-insights', [SmartAssistController::class, 'plInsights'])
        ->middleware('permission:finance.smart.view');

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

    Route::get('/open-items/aging', [OpenItemController::class, 'aging'])
        ->middleware('permission:finance.ar.view');
    Route::get('/open-items', [OpenItemController::class, 'index'])
        ->middleware('permission:finance.ar.view');
    Route::post('/open-items', [OpenItemController::class, 'store'])
        ->middleware('permission:finance.ar.manage');
    Route::post('/open-items/{openItem}/allocate', [OpenItemController::class, 'allocate'])
        ->middleware('permission:finance.ar.manage');

    Route::get('/bank-statements', [BankReconciliationController::class, 'index'])
        ->middleware('permission:finance.treasury.view');
    Route::post('/bank-statements', [BankReconciliationController::class, 'store'])
        ->middleware('permission:finance.treasury.manage');
    Route::post('/bank-statement-lines/{line}/match', [BankReconciliationController::class, 'matchLine'])
        ->middleware('permission:finance.treasury.manage');

    Route::get('/tax/rates', [TaxController::class, 'rates'])
        ->middleware('permission:finance.tax.view');
    Route::post('/tax/rates', [TaxController::class, 'storeRate'])
        ->middleware('permission:finance.tax.manage');
    Route::post('/tax/split', [TaxController::class, 'split'])
        ->middleware('permission:finance.tax.view');
    Route::get('/tax/transactions', [TaxController::class, 'transactions'])
        ->middleware('permission:finance.tax.view');
    Route::post('/tax/transactions', [TaxController::class, 'recordTransaction'])
        ->middleware('permission:finance.tax.manage');

    Route::get('/moodian/submissions', [MoodianController::class, 'index'])
        ->middleware('permission:finance.moodian.view');
    Route::post('/moodian/submit', [MoodianController::class, 'submit'])
        ->middleware('permission:finance.moodian.submit');
    Route::post('/moodian/submissions/{submission}/poll', [MoodianController::class, 'poll'])
        ->middleware('permission:finance.moodian.view');

    Route::get('/compliance-alerts', [ComplianceAlertController::class, 'index'])
        ->middleware('permission:finance.compliance.view');
    Route::post('/compliance-alerts/scan', [ComplianceAlertController::class, 'scan'])
        ->middleware('permission:finance.compliance.manage');
    Route::post('/compliance-alerts/{alert}/resolve', [ComplianceAlertController::class, 'resolve'])
        ->middleware('permission:finance.compliance.manage');

    Route::get('/suggested-journals', [SuggestedJournalController::class, 'index'])
        ->middleware('permission:finance.suggest.view');
    Route::post('/suggested-journals', [SuggestedJournalController::class, 'store'])
        ->middleware('permission:finance.suggest.manage');
    Route::get('/suggested-journals/{suggested}', [SuggestedJournalController::class, 'show'])
        ->middleware('permission:finance.suggest.view');
    Route::post('/suggested-journals/{suggested}/accept', [SuggestedJournalController::class, 'accept'])
        ->middleware('permission:finance.suggest.decide');
    Route::post('/suggested-journals/{suggested}/reject', [SuggestedJournalController::class, 'reject'])
        ->middleware('permission:finance.suggest.decide');

    Route::get('/account-determination-rules', [SuggestedJournalController::class, 'rules'])
        ->middleware('permission:finance.suggest.view');
    Route::post('/account-determination-rules', [SuggestedJournalController::class, 'storeRule'])
        ->middleware('permission:finance.suggest.manage');

    Route::get('/fixed-assets', [FixedAssetController::class, 'index'])
        ->middleware('permission:finance.fa.view');
    Route::post('/fixed-assets', [FixedAssetController::class, 'store'])
        ->middleware('permission:finance.fa.manage');
    Route::post('/fixed-assets/run-depreciation', [FixedAssetController::class, 'runDepreciation'])
        ->middleware('permission:finance.fa.depreciate');

    Route::post('/intercompany/account-maps', [IntercompanyController::class, 'upsertMap'])
        ->middleware('permission:finance.ic.manage');
    Route::post('/intercompany/pairs', [IntercompanyController::class, 'createPair'])
        ->middleware('permission:finance.ic.manage');
    Route::post('/intercompany/eliminations', [IntercompanyController::class, 'createElimination'])
        ->middleware('permission:finance.ic.manage');

    // FIN-P6 smart assist
    Route::post('/smart/suggest-accounts', [SmartAssistController::class, 'suggestAccounts'])
        ->middleware('permission:finance.smart.view');
    Route::post('/smart/account-decision', [SmartAssistController::class, 'recordAccountDecision'])
        ->middleware('permission:finance.smart.decide');
    Route::post('/smart/nl-draft', [SmartAssistController::class, 'nlDraft'])
        ->middleware('permission:finance.smart.nl');
});
