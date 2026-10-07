<?php

declare(strict_types=1);

/**
 * FinancialAccounting API routes — versioned under /api/v1/finance
 * Loaded by the application route registrar when the module is wired.
 *
 * P0 routes will be registered here after FIN-P0-16.
 */

use Illuminate\Support\Facades\Route;

Route::prefix('v1/finance')->middleware(['api'])->group(function () {
    // Placeholder — CoA, journals, periods, reports land in FIN-P0-16.
});
