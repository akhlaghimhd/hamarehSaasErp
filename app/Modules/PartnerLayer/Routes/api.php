<?php

use Illuminate\Support\Facades\Route;
use App\Modules\PartnerLayer\Controllers\PartnerController;

/*
|--------------------------------------------------------------------------
| PartnerLayer API Routes (Layer 3)
|--------------------------------------------------------------------------
| Loaded by ModuleServiceProvider with prefix: /api/partner-layer
| Middleware 'api' is already applied by the provider.
|
| P3-A1 — Partner CRUD core endpoints.
*/

Route::middleware([
    'auth:sanctum',
    'tenant.context',
    'load.scopes',
])->group(function () {

    Route::get('/partners', [PartnerController::class, 'index'])
        ->middleware('permission:partner.partner.view')
        ->name('partner-layer.partners.index');

    Route::post('/partners', [PartnerController::class, 'store'])
        ->middleware('permission:partner.partner.create')
        ->name('partner-layer.partners.store');

    Route::get('/partners/{partner}', [PartnerController::class, 'show'])
        ->middleware('permission:partner.partner.view')
        ->name('partner-layer.partners.show');

    Route::put('/partners/{partner}', [PartnerController::class, 'update'])
        ->middleware('permission:partner.partner.update')
        ->name('partner-layer.partners.update');

    Route::delete('/partners/{partner}', [PartnerController::class, 'destroy'])
        ->middleware('permission:partner.partner.delete')
        ->name('partner-layer.partners.delete');

});
