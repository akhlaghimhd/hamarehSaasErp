<?php

use Illuminate\Support\Facades\Route;
use App\Modules\SaasAdmin\Controllers\TenantController;
use App\Modules\SaasAdmin\Controllers\PlanController;
use App\Modules\SaasAdmin\Controllers\SubscriptionController;
use App\Modules\SaasAdmin\Controllers\AddonController;
use App\Modules\SaasAdmin\Controllers\CouponController;
use App\Modules\SaasAdmin\Controllers\InvoiceController;

/*
|--------------------------------------------------------------------------
| Saas Admin API Routes
|--------------------------------------------------------------------------
| Automatic prefix from ModuleServiceProvider: /api/saas-admin
*/

Route::middleware('auth:api')->group(function () {

    // Tenants
    Route::post('/tenants', [TenantController::class, 'store'])->name('saas-admin.tenants.store');

    // Plans
    Route::get('/plans', [PlanController::class, 'index'])->name('saas-admin.plans.index');
    Route::post('/plans', [PlanController::class, 'store'])->name('saas-admin.plans.store');

    // Subscriptions
    Route::post('/subscriptions', [SubscriptionController::class, 'store'])->name('saas-admin.subscriptions.store');

    // Addons
    Route::get('/addons', [AddonController::class, 'index'])->name('saas-admin.addons.index');
    Route::post('/addons', [AddonController::class, 'store'])->name('saas-admin.addons.store');

    // Coupons
    Route::post('/coupons', [CouponController::class, 'store'])->name('saas-admin.coupons.store');

    // Invoices
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('saas-admin.invoices.store');
});