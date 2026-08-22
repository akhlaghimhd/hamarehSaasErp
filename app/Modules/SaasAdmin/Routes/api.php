<?php

use Illuminate\Support\Facades\Route;
use App\Modules\SaasAdmin\Controllers\TenantController;

/*
|--------------------------------------------------------------------------
| Saas Admin API Routes
|--------------------------------------------------------------------------
| Prefix پیش‌فرض: /api/saas-admin
*/

Route::prefix('saas-admin')->group(function () {
    
    // روت‌های محافظت شده با توکن JWT
    Route::middleware('auth:api')->group(function () {
        
        // ساخت شرکت جدید
        Route::post('/tenants', [TenantController::class, 'store'])->name('saas-admin.tenants.store');
        
    });

});