<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Manufacturing\Controllers\WorkCenterController;
use App\Modules\Manufacturing\Controllers\BomController;

/*
|--------------------------------------------------------------------------
| Manufacturing Module API Routes
|--------------------------------------------------------------------------
|
| All manufacturing domain endpoints are defined here.
| Foreign keys across modules are prohibited (logical UUID references only).
| Tenant context is enforced via 'tenant' middleware.
|
*/

Route::prefix('api/manufacturing')->middleware(['api', 'auth:sanctum', 'tenant'])->group(function () {

    // --------------------------------------------------------------------
    // Work Centers (ایستگاه‌های کاری و خطوط تولید)
    // --------------------------------------------------------------------
    Route::post('/work-centers', [WorkCenterController::class, 'store']);

    // --------------------------------------------------------------------
    // Bill of Materials - BOM (درختواره مواد و فرمولاسیون ساخت)
    // --------------------------------------------------------------------
    Route::post('/boms', [BomController::class, 'store']);

});