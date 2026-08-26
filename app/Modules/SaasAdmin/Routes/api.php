<?php

use Illuminate\Support\Facades\Route;
use App\Modules\SaasAdmin\Controllers\TenantController;
use App\Modules\SaasAdmin\Controllers\PlanController;
use App\Modules\SaasAdmin\Controllers\SubscriptionController;
use App\Modules\SaasAdmin\Controllers\InvoiceController;
use App\Modules\SaasAdmin\Controllers\AddonController;
use App\Modules\SaasAdmin\Controllers\CouponController;

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

    // Plans
    Route::get('/plans', [PlanController::class, 'index'])
        ->middleware('permission:saas-admin.plan.view')
        ->name('saas-admin.plans.index');

    Route::post('/plans', [PlanController::class, 'store'])
        ->middleware('permission:saas-admin.plan.create')
        ->name('saas-admin.plans.store');

    // Subscriptions
    Route::post('/subscriptions', [SubscriptionController::class, 'store'])
        ->middleware('permission:saas-admin.subscription.create')
        ->name('saas-admin.subscriptions.store');

    Route::post('/subscriptions/{subscriptionId}/cancel', [SubscriptionController::class, 'cancel'])
        ->middleware('permission:saas-admin.subscription.cancel')
        ->name('saas-admin.subscriptions.cancel');

    // Invoices
    Route::post('/invoices', [InvoiceController::class, 'store'])
        ->middleware('permission:saas-admin.invoice.create')
        ->name('saas-admin.invoices.store');

    // Addons
    Route::get('/addons', [AddonController::class, 'index'])
        ->middleware('permission:saas-admin.addon.view')
        ->name('saas-admin.addons.index');

    Route::post('/addons', [AddonController::class, 'store'])
        ->middleware('permission:saas-admin.addon.create')
        ->name('saas-admin.addons.store');

    // Coupons
    Route::post('/coupons', [CouponController::class, 'store'])
        ->middleware('permission:saas-admin.coupon.create')
        ->name('saas-admin.coupons.store');

});