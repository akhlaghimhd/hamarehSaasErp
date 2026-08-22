<?php

use Illuminate\Support\Facades\Route;
use App\Modules\IdentityCore\Controllers\RoleController;
use App\Modules\IdentityCore\Controllers\AuthController;
use App\Base\Http\Middleware\TenantContextMiddleware;

Route::prefix('identity')->group(function () {

    // ۱. روت‌های عمومی (فقط Tenant Context)
    Route::middleware([TenantContextMiddleware::class])->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/auth/login', [AuthController::class, 'login']);
    });

    // ۲. روت‌های محافظت‌شده (Tenant Context + احراز هویت)
    Route::middleware([TenantContextMiddleware::class, 'auth:sanctum'])->group(function () {
        Route::prefix('roles')->group(function () {
            Route::get('/', [RoleController::class, 'index']);
            Route::post('/', [RoleController::class, 'store']);
            Route::post('/assign', [RoleController::class, 'assign']);
            Route::post('/assign-permissions', [RoleController::class, 'assignPermissions']);
        });
    });

});