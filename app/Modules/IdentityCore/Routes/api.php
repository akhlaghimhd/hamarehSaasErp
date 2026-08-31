<?php

use Illuminate\Support\Facades\Route;
use App\Modules\IdentityCore\Controllers\RoleController;
use App\Modules\IdentityCore\Controllers\PermissionController;
use App\Modules\IdentityCore\Controllers\UserController;
use App\Modules\IdentityCore\Controllers\AuthController;
use App\Modules\IdentityCore\Controllers\ScopeController;
use App\Base\Http\Middleware\TenantContextMiddleware;

Route::prefix('identity')->group(function () {

    // 1. Public routes (Tenant Context only)
    Route::middleware([TenantContextMiddleware::class])->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/auth/login', [AuthController::class, 'login']);
    });

    // 2. Protected routes (Tenant Context + auth + scopes + permission)
    Route::middleware([TenantContextMiddleware::class, 'auth:sanctum', 'load.scopes'])->group(function () {

        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Users (tenant membership management)
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:identity.user.view');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('permission:identity.user.create');
        Route::get('/users/{id}', [UserController::class, 'show'])
            ->middleware('permission:identity.user.view');
        Route::put('/users/{id}', [UserController::class, 'update'])
            ->middleware('permission:identity.user.update');
        Route::delete('/users/{id}', [UserController::class, 'destroy'])
            ->middleware('permission:identity.user.delete');

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

        // Scopes — specific paths BEFORE /{id}
        Route::prefix('scopes')->group(function () {
            Route::get('/', [ScopeController::class, 'index'])
                ->middleware('permission:identity.scope.view');
            Route::post('/', [ScopeController::class, 'store'])
                ->middleware('permission:identity.scope.create');

            Route::post('/assign', [ScopeController::class, 'assign'])
                ->middleware('permission:identity.scope.assign');
            Route::post('/unassign', [ScopeController::class, 'unassign'])
                ->middleware('permission:identity.scope.assign');
            Route::get('/user/{tenantUserId}', [ScopeController::class, 'userScopes'])
                ->middleware('permission:identity.scope.view');

            Route::get('/{id}', [ScopeController::class, 'show'])
                ->middleware('permission:identity.scope.view');
            Route::put('/{id}', [ScopeController::class, 'update'])
                ->middleware('permission:identity.scope.update');
            Route::delete('/{id}', [ScopeController::class, 'destroy'])
                ->middleware('permission:identity.scope.delete');
        });
    });
});
