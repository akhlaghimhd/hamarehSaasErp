<?php

use Illuminate\Support\Facades\Route;
use App\Modules\IdentityCore\Controllers\RoleController;
use App\Modules\IdentityCore\Controllers\PermissionController;
use App\Modules\IdentityCore\Controllers\UserController;
use App\Modules\IdentityCore\Controllers\AuthController;
use App\Base\Http\Middleware\TenantContextMiddleware;

Route::prefix('identity')->group(function () {

    // ۱. روت‌های عمومی (فقط Tenant Context)
    Route::middleware([TenantContextMiddleware::class])->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/auth/login', [AuthController::class, 'login']);
    });

    // ۲. روت‌های محافظت‌شده (Tenant Context + احراز هویت + Permission)
    Route::middleware([TenantContextMiddleware::class, 'auth:sanctum'])->group(function () {

        // Users (مدیریت کاربران مستأجر)
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:identity.user.view');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('permission:identity.user.create');
        Route::get('/users/{id}', [UserController::class, 'show'])
            ->middleware('permission:identity.user.view');

        // Permissions
        Route::get('/permissions', [PermissionController::class, 'index'])
            ->middleware('permission:identity.permission.view');
        Route::post('/permissions', [PermissionController::class, 'store'])
            ->middleware('permission:identity.permission.create');

        // Roles
        Route::prefix('roles')->group(function () {
            Route::get('/', [RoleController::class, 'index'])
                ->middleware('permission:identity.role.view');
            Route::post('/', [RoleController::class, 'store'])
                ->middleware('permission:identity.role.create');
            Route::post('/assign', [RoleController::class, 'assign'])
                ->middleware('permission:identity.role.assign');
            Route::post('/assign-permissions', [RoleController::class, 'assignPermissions'])
                ->middleware('permission:identity.role.assign-permissions');
        });
    });
});