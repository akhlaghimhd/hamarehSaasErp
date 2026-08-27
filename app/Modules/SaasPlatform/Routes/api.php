<?php

use Illuminate\Support\Facades\Route;
use App\Modules\SaasPlatform\Controllers\TenantController;
use App\Modules\SaasPlatform\Controllers\PlanController;
use App\Modules\SaasPlatform\Controllers\SubscriptionController;
use App\Modules\SaasPlatform\Controllers\InvoiceController;
use App\Modules\SaasPlatform\Controllers\AddonController;
use App\Modules\SaasPlatform\Controllers\CouponController;

/*
|--------------------------------------------------------------------------
| SaaS Platform API Routes (Layer 1 - SaaS Business)
|--------------------------------------------------------------------------
| Loaded by ModuleServiceProvider with prefix: /api/saas-platform
| Middleware 'api' is already applied by the provider.
|
| Temporary dual-permission strategy:
| - Permission codes still use saas-admin.* so existing roles/tests keep working
| - Route names use saas-platform.*
| - Final cleanup of permission codes comes in a later controlled step
*/

Route::middleware(['auth:sanctum', 'tenant.context', 'load.scopes'])->group(function () {

    // Tenants
    Route::post('/tenants', [TenantController::class, 'store'])
        ->middleware('permission:saas-admin.tenant.create')
        ->name('saas-platform.tenants.store');

    // Plans
    Route::get('/plans', [PlanController::class, 'index'])
        ->middleware('permission:saas-admin.plan.view')
        ->name('saas-platform.plans.index');

    Route::post('/plans', [PlanController::class, 'store'])
        ->middleware('permission:saas-admin.plan.create')
        ->name('saas-platform.plans.store');

    // Subscriptions
    Route::post('/subscriptions', [SubscriptionController::class, 'store'])
        ->middleware('permission:saas-admin.subscription.create')
        ->name('saas-platform.subscriptions.store');

    Route::post('/subscriptions/{subscriptionId}/cancel', [SubscriptionController::class, 'cancel'])
        ->middleware('permission:saas-admin.subscription.cancel')
        ->name('saas-platform.subscriptions.cancel');

    // Invoices
    Route::post('/invoices', [InvoiceController::class, 'store'])
        ->middleware('permission:saas-admin.invoice.create')
        ->name('saas-platform.invoices.store');

    // Addons
    Route::get('/addons', [AddonController::class, 'index'])
        ->middleware('permission:saas-admin.addon.view')
        ->name('saas-platform.addons.index');

    Route::post('/addons', [AddonController::class, 'store'])
        ->middleware('permission:saas-admin.addon.create')
        ->name('saas-platform.addons.store');

    // Coupons
    Route::post('/coupons', [CouponController::class, 'store'])
        ->middleware('permission:saas-admin.coupon.create')
        ->name('saas-platform.coupons.store');

});
