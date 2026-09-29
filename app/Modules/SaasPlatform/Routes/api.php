<?php

use Illuminate\Support\Facades\Route;
use App\Modules\SaasPlatform\Controllers\TenantController;
use App\Modules\SaasPlatform\Controllers\PlanController;
use App\Modules\SaasPlatform\Controllers\SubscriptionController;
use App\Modules\SaasPlatform\Controllers\InvoiceController;
use App\Modules\SaasPlatform\Controllers\AddonController;
use App\Modules\SaasPlatform\Controllers\CouponController;
use App\Modules\SaasPlatform\Controllers\FeatureCatalogController;

Route::middleware(['auth:sanctum', 'tenant.context', 'load.scopes'])->group(function () {

    Route::post('/tenants', [TenantController::class, 'store'])
        ->middleware('permission:saas-admin.tenant.create')
        ->name('saas-platform.tenants.store');

    Route::get('/plans', [PlanController::class, 'index'])
        ->middleware('permission:saas-admin.plan.view')
        ->name('saas-platform.plans.index');

    Route::post('/plans', [PlanController::class, 'store'])
        ->middleware('permission:saas-admin.plan.create')
        ->name('saas-platform.plans.store');

    Route::post('/subscriptions', [SubscriptionController::class, 'store'])
        ->middleware('permission:saas-admin.subscription.create')
        ->name('saas-platform.subscriptions.store');

    Route::post('/subscriptions/{subscriptionId}/cancel', [SubscriptionController::class, 'cancel'])
        ->middleware('permission:saas-admin.subscription.cancel')
        ->name('saas-platform.subscriptions.cancel');

    Route::post('/invoices', [InvoiceController::class, 'store'])
        ->middleware('permission:saas-admin.invoice.create')
        ->name('saas-platform.invoices.store');

    Route::get('/addons', [AddonController::class, 'index'])
        ->middleware('permission:saas-admin.addon.view')
        ->name('saas-platform.addons.index');

    Route::post('/addons', [AddonController::class, 'store'])
        ->middleware('permission:saas-admin.addon.create')
        ->name('saas-platform.addons.store');

    Route::post('/coupons', [CouponController::class, 'store'])
        ->middleware('permission:saas-admin.coupon.create')
        ->name('saas-platform.coupons.store');

    Route::get('/feature-catalog', [FeatureCatalogController::class, 'catalog'])
        ->name('saas-platform.feature-catalog.index');

    Route::get('/feature-entitlements', [FeatureCatalogController::class, 'myEntitlements'])
        ->name('saas-platform.feature-entitlements.mine');

    Route::post('/feature-entitlements', [FeatureCatalogController::class, 'setEntitlement'])
        ->middleware('permission:saas-admin.feature.manage')
        ->name('saas-platform.feature-entitlements.set');

    // PLT-W1-03 explicit freeze / unfreeze
    Route::post('/feature-entitlements/freeze', [FeatureCatalogController::class, 'freeze'])
        ->middleware('permission:saas-admin.feature.manage')
        ->name('saas-platform.feature-entitlements.freeze');

    Route::post('/feature-entitlements/unfreeze', [FeatureCatalogController::class, 'unfreeze'])
        ->middleware('permission:saas-admin.feature.manage')
        ->name('saas-platform.feature-entitlements.unfreeze');

});
