<?php

use Illuminate\Support\Facades\Route;
use App\Modules\IdentityCore\Controllers\RoleController;
use App\Modules\IdentityCore\Controllers\PermissionController;
use App\Modules\IdentityCore\Controllers\UserController;
use App\Modules\IdentityCore\Controllers\AuthController;
use App\Modules\IdentityCore\Controllers\ScopeController;
use App\Modules\IdentityCore\Controllers\ProfileController;
use App\Modules\IdentityCore\Controllers\MembershipHistoryController;
use App\Base\Http\Middleware\TenantContextMiddleware;

Route::prefix('identity')->group(function () {

    Route::middleware([TenantContextMiddleware::class])->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/auth/login', [AuthController::class, 'login']);
    });

    Route::middleware([TenantContextMiddleware::class, 'auth:sanctum', 'load.scopes'])->group(function () {

        Route::post('/auth/logout', [AuthController::class, 'logout']);

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

        // Profiles — /me before /{userId}; self-service does not require profile permission
        Route::prefix('profiles')->group(function () {
            Route::get('/me', [ProfileController::class, 'me']);
            Route::put('/me', [ProfileController::class, 'upsertMe']);

            Route::get('/{userId}', [ProfileController::class, 'show'])
                ->middleware('permission:identity.profile.view');
            Route::put('/{userId}', [ProfileController::class, 'upsert'])
                ->middleware('permission:identity.profile.update');
            Route::delete('/{userId}', [ProfileController::class, 'destroy'])
                ->middleware('permission:identity.profile.delete');
        });

        // Membership history (read-only audit)
        Route::prefix('membership-histories')->group(function () {
            Route::get('/', [MembershipHistoryController::class, 'index'])
                ->middleware('permission:identity.membership_history.view');
            Route::get('/user/{tenantUserId}', [MembershipHistoryController::class, 'byTenantUser'])
                ->middleware('permission:identity.membership_history.view');
        });

        Route::prefix('permissions')->group(function () {
            Route::get('/', [PermissionController::class, 'index'])
                ->middleware('permission:identity.permission.view');
            Route::post('/', [PermissionController::class, 'store'])
                ->middleware('permission:identity.permission.create');
            Route::get('/{id}', [PermissionController::class, 'show'])
                ->middleware('permission:identity.permission.view');
            Route::put('/{id}', [PermissionController::class, 'update'])
                ->middleware('permission:identity.permission.update');
            Route::delete('/{id}', [PermissionController::class, 'destroy'])
                ->middleware('permission:identity.permission.delete');
        });

        Route::prefix('roles')->group(function () {
            Route::get('/', [RoleController::class, 'index'])
                ->middleware('permission:identity.role.view');
            Route::post('/', [RoleController::class, 'store'])
                ->middleware('permission:identity.role.create');
            Route::post('/assign', [RoleController::class, 'assign'])
                ->middleware('permission:identity.role.assign');
            Route::post('/assign-permissions', [RoleController::class, 'assignPermissions'])
                ->middleware('permission:identity.role.assign-permissions');

            Route::get('/{id}', [RoleController::class, 'show'])
                ->middleware('permission:identity.role.view');
            Route::put('/{id}', [RoleController::class, 'update'])
                ->middleware('permission:identity.role.update');
            Route::delete('/{id}', [RoleController::class, 'destroy'])
                ->middleware('permission:identity.role.delete');
        });

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
