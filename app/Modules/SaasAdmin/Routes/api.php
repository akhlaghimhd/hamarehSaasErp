<?php

use Illuminate\Support\Facades\Route;
use App\Modules\SaasAdmin\Controllers\TenantController;

/*
|--------------------------------------------------------------------------
| Saas Admin API Routes
|--------------------------------------------------------------------------
| Loaded by ModuleServiceProvider with prefix: /api/saas-admin
| Middleware 'api' is already applied by the provider.
*/

Route::middleware(['auth:sanctum', 'tenant.context', 'load.scopes'])->group(function () {

    // Tenants
    Route::post('/tenants', [TenantController::class, 'store'])
        ->middleware('permission:saas-admin.tenant.create')
        ->name('saas-admin.tenants.store');

});