<?php

use Illuminate\Support\Facades\Route;
use App\Modules\IdentityCore\Controllers\RoleController;
use App\Modules\IdentityCore\Controllers\PermissionController;
use App\Modules\IdentityCore\Controllers\UserController;
use App\Modules\IdentityCore\Controllers\ScopeController;
use App\Modules\IdentityCore\Controllers\AuthController;
use App\Modules\IdentityCore\Controllers\MfaController;
use App\Modules\IdentityCore\Controllers\SsoController;
use App\Modules\IdentityCore\Controllers\ProfileController;
use App\Modules\IdentityCore\Controllers\MembershipHistoryController;
use App\Modules\IdentityCore\Controllers\SodController;
use App\Modules\IdentityCore\Controllers\AccessCertificationController;
use App\Modules\IdentityCore\Controllers\PrivilegedAccessController;
use App\Base\Http\Middleware\TenantContextMiddleware;

Route::prefix('identity')->group(function () {

    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/otp/request', [AuthController::class, 'requestOtp']);
    Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp']);

    Route::post('/auth/forgot-password/request', [AuthController::class, 'forgotPasswordRequest']);
    Route::post('/auth/forgot-password/confirm', [AuthController::class, 'forgotPasswordConfirm']);

    Route::get('/auth/sso/providers', [SsoController::class, 'providers']);
    Route::post('/auth/sso/begin', [SsoController::class, 'begin']);
    Route::post('/auth/sso/callback', [SsoController::class, 'callback']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/auth/select-tenant', [AuthController::class, 'selectTenant']);

        Route::post('/auth/set-password', [AuthController::class, 'setPassword']);
        Route::post('/auth/mfa/verify', [MfaController::class, 'verifyChallenge']);
    });

    Route::middleware([TenantContextMiddleware::class])->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
    });

    Route::middleware([TenantContextMiddleware::class, 'auth:sanctum', 'load.scopes'])->group(function () {

        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

        Route::get('/auth/mfa/status', [MfaController::class, 'status']);
        Route::post('/auth/mfa/enable', [MfaController::class, 'beginEnable']);
        Route::post('/auth/mfa/confirm', [MfaController::class, 'confirmEnable']);
        Route::post('/auth/mfa/disable', [MfaController::class, 'disable']);

        Route::post('/auth/sso/providers', [SsoController::class, 'upsertProvider'])
            ->middleware('permission:identity.mfa.manage');

        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:identity.user.view');
        Route::get('/users/email-host', [UserController::class, 'emailHost'])
            ->middleware('permission:identity.user.create');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('permission:identity.user.create');
        Route::post('/users/{id}/restore', [UserController::class, 'restore'])
            ->middleware('permission:identity.user.restore');
        Route::get('/users/{id}', [UserController::class, 'show'])
            ->middleware('permission:identity.user.view');
        Route::put('/users/{id}', [UserController::class, 'update'])
            ->middleware('permission:identity.user.update');
        Route::delete('/users/{id}', [UserController::class, 'destroy'])
            ->middleware('permission:identity.user.delete');

        Route::prefix('profiles')->group(function () {
            Route::get('/me', [ProfileController::class, 'me']);
            Route::put('/me', [ProfileController::class, 'upsertMe']);
            Route::post('/me/avatar', [ProfileController::class, 'uploadAvatarMe']);
            Route::get('/me/avatar', [ProfileController::class, 'streamAvatarMe']);
            Route::post('/me/mobile/request', [ProfileController::class, 'requestMobileChange']);
            Route::post('/me/mobile/verify', [ProfileController::class, 'verifyMobileChange']);

            Route::get('/{userId}/avatar', [ProfileController::class, 'streamAvatar'])
                ->middleware('permission:identity.profile.view');
            Route::get('/{userId}', [ProfileController::class, 'show'])
                ->middleware('permission:identity.profile.view');
            Route::put('/{userId}', [ProfileController::class, 'upsert'])
                ->middleware('permission:identity.profile.update');
            Route::post('/{userId}/approve-address', [ProfileController::class, 'approveAddress'])
                ->middleware('permission:identity.profile.update');
            Route::delete('/{userId}', [ProfileController::class, 'destroy'])
                ->middleware('permission:identity.profile.delete');
        });

        Route::prefix('membership-histories')->group(function () {
            Route::get('/', [MembershipHistoryController::class, 'index'])
                ->middleware('permission:identity.membership_history.view');
            Route::get('/user/{tenantUserId}', [MembershipHistoryController::class, 'byTenantUser'])
                ->middleware('permission:identity.membership_history.view');
        });

        Route::prefix('access-certifications')->group(function () {
            Route::get('/', [AccessCertificationController::class, 'index'])
                ->middleware('permission:identity.access_cert.view');
            Route::post('/', [AccessCertificationController::class, 'store'])
                ->middleware('permission:identity.access_cert.manage');
            Route::get('/{id}', [AccessCertificationController::class, 'show'])
                ->middleware('permission:identity.access_cert.view');
            Route::post('/{id}/open', [AccessCertificationController::class, 'open'])
                ->middleware('permission:identity.access_cert.manage');
            Route::get('/{id}/items', [AccessCertificationController::class, 'items'])
                ->middleware('permission:identity.access_cert.view');
            Route::post('/{id}/complete', [AccessCertificationController::class, 'complete'])
                ->middleware('permission:identity.access_cert.manage');
            Route::post('/items/{itemId}/certify', [AccessCertificationController::class, 'certify'])
                ->middleware('permission:identity.access_cert.certify');
        });

        // ID-W2-02 Privileged / Emergency Access
        Route::prefix('privileged-access')->group(function () {
            Route::get('/', [PrivilegedAccessController::class, 'index'])
                ->middleware('permission:identity.privileged.view');
            Route::post('/request', [PrivilegedAccessController::class, 'request'])
                ->middleware('permission:identity.privileged.request');
            Route::post('/{id}/approve', [PrivilegedAccessController::class, 'approve'])
                ->middleware('permission:identity.privileged.approve');
            Route::post('/{id}/deny', [PrivilegedAccessController::class, 'deny'])
                ->middleware('permission:identity.privileged.approve');
            Route::post('/{id}/revoke', [PrivilegedAccessController::class, 'revoke'])
                ->middleware('permission:identity.privileged.approve');
            Route::post('/mark-role', [PrivilegedAccessController::class, 'markRole'])
                ->middleware('permission:identity.privileged.approve');
        });

        Route::prefix('roles')->group(function () {
            Route::get('/', [RoleController::class, 'index'])
                ->middleware('permission:identity.role.view');
            Route::post('/', [RoleController::class, 'store'])
                ->middleware('permission:identity.role.create');
            Route::get('/{id}', [RoleController::class, 'show'])
                ->middleware('permission:identity.role.view');
            Route::put('/{id}', [RoleController::class, 'update'])
                ->middleware('permission:identity.role.update');
            Route::delete('/{id}', [RoleController::class, 'destroy'])
                ->middleware('permission:identity.role.delete');
            Route::post('/{id}/permissions', [RoleController::class, 'assignPermissions'])
                ->middleware('permission:identity.role.assign-permissions');
            Route::get('/user/{userId}', [RoleController::class, 'userRoles'])
                ->middleware('permission:identity.role.view');
            Route::post('/user/{userId}', [RoleController::class, 'assignRole'])
                ->middleware('permission:identity.role.assign');
        });

        Route::prefix('permissions')->group(function () {
            Route::get('/', [PermissionController::class, 'index'])
                ->middleware('permission:identity.permission.view');
            Route::get('/{id}', [PermissionController::class, 'show'])
                ->middleware('permission:identity.permission.view');
            Route::put('/{id}', [PermissionController::class, 'update'])
                ->middleware('permission:identity.permission.update');
        });

        Route::prefix('scopes')->group(function () {
            Route::get('/', [ScopeController::class, 'index'])
                ->middleware('permission:identity.scope.view');
            Route::post('/', [ScopeController::class, 'store'])
                ->middleware('permission:identity.scope.create');
            Route::get('/{id}', [ScopeController::class, 'show'])
                ->middleware('permission:identity.scope.view');
            Route::put('/{id}', [ScopeController::class, 'update'])
                ->middleware('permission:identity.scope.update');
            Route::delete('/{id}', [ScopeController::class, 'destroy'])
                ->middleware('permission:identity.scope.delete');
            Route::post('/user/{userId}', [ScopeController::class, 'assignToUser'])
                ->middleware('permission:identity.scope.assign');
            Route::get('/user/{userId}', [ScopeController::class, 'listForUser'])
                ->middleware('permission:identity.scope.view');
        });

        Route::prefix('sod-rules')->group(function () {
            Route::get('/', [SodController::class, 'index'])
                ->middleware('permission:identity.sod.view');
            Route::post('/', [SodController::class, 'store'])
                ->middleware('permission:identity.sod.manage');
            Route::post('/evaluate', [SodController::class, 'evaluate'])
                ->middleware('permission:identity.sod.view');
            Route::put('/{id}', [SodController::class, 'update'])
                ->middleware('permission:identity.sod.manage');
            Route::delete('/{id}', [SodController::class, 'destroy'])
                ->middleware('permission:identity.sod.manage');
        });
    });
});
