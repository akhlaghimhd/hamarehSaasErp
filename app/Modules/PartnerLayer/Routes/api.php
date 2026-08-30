<?php

use Illuminate\Support\Facades\Route;
use App\Modules\PartnerLayer\Controllers\PartnerController;
use App\Modules\PartnerLayer\Controllers\PartnerUserController;

/*
|--------------------------------------------------------------------------
| PartnerLayer API Routes (Layer 3)
|--------------------------------------------------------------------------
| Loaded by ModuleServiceProvider with prefix: /api/partner-layer
| Middleware 'api' is already applied by the provider.
|
| P3-A1 — Partner CRUD + PartnerUser (logical user link) endpoints.
*/

Route::middleware([
    'auth:sanctum',
    'tenant.context',
    'load.scopes',
])->group(function () {

    // Partner core
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

    // PartnerUser — logical link of IdentityCore user_id to Partner
    Route::get('/partner-users', [PartnerUserController::class, 'index'])
        ->middleware('permission:partner.partner_user.view')
        ->name('partner-layer.partner-users.index');

    Route::post('/partner-users', [PartnerUserController::class, 'store'])
        ->middleware('permission:partner.partner_user.create')
        ->name('partner-layer.partner-users.store');

    Route::get('/partner-users/{partnerUser}', [PartnerUserController::class, 'show'])
        ->middleware('permission:partner.partner_user.view')
        ->name('partner-layer.partner-users.show');

    Route::put('/partner-users/{partnerUser}', [PartnerUserController::class, 'update'])
        ->middleware('permission:partner.partner_user.update')
        ->name('partner-layer.partner-users.update');

    Route::delete('/partner-users/{partnerUser}', [PartnerUserController::class, 'destroy'])
        ->middleware('permission:partner.partner_user.delete')
        ->name('partner-layer.partner-users.delete');

});
