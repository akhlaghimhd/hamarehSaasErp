<?php

declare(strict_types=1);

/**
 * FinancialAccounting API routes.
 * Loaded by ModuleServiceProvider as /api/v1/financial-accounting/...
 * Product surface also targets /api/v1/finance/... (registered in FIN-P0-16).
 */

use Illuminate\Support\Facades\Route;

// Placeholder group — CoA, journals, periods, reports land in FIN-P0-16.
Route::middleware(['tenant.context'])->group(function () {
    //
});
