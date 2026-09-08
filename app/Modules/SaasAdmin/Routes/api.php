<?php

use Illuminate\Support\Facades\Route;
use App\Modules\SaasAdmin\Controllers\AdminUserController;
use App\Modules\SaasAdmin\Controllers\AdminRoleController;
use App\Modules\SaasAdmin\Controllers\SystemSettingController;
use App\Modules\SaasAdmin\Controllers\NotificationController;
use App\Modules\SaasAdmin\Controllers\SupportTicketController;

/*
|--------------------------------------------------------------------------
| Saas Admin API Routes (Layer 2 - Platform Administration)
|--------------------------------------------------------------------------
| Loaded by ModuleServiceProvider with prefix: /api/saas-admin
| Middleware 'api' is already applied by the provider.
|
| Layer 1 business routes (Tenant, Plan, Subscription, Invoice, Addon, Coupon)
| live in SaasPlatform module under /api/saas-platform.
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // Admin Users
    Route::get('/admin-users', [AdminUserController::class, 'index'])
        ->middleware('permission:saas-admin.admin_user.view')
        ->name('saas-admin.admin-users.index');

    Route::get('/admin-users/{id}', [AdminUserController::class, 'show'])
        ->middleware('permission:saas-admin.admin_user.view')
        ->name('saas-admin.admin-users.show');

    Route::post('/admin-users', [AdminUserController::class, 'store'])
        ->middleware('permission:saas-admin.admin_user.create')
        ->name('saas-admin.admin-users.store');

    Route::put('/admin-users/{id}', [AdminUserController::class, 'update'])
        ->middleware('permission:saas-admin.admin_user.update')
        ->name('saas-admin.admin-users.update');

    Route::delete('/admin-users/{id}', [AdminUserController::class, 'destroy'])
        ->middleware('permission:saas-admin.admin_user.delete')
        ->name('saas-admin.admin-users.destroy');

    // Admin Roles
    Route::get('/admin-roles', [AdminRoleController::class, 'index'])
        ->middleware('permission:saas-admin.admin_role.view')
        ->name('saas-admin.admin-roles.index');

    Route::get('/admin-roles/{id}', [AdminRoleController::class, 'show'])
        ->middleware('permission:saas-admin.admin_role.view')
        ->name('saas-admin.admin-roles.show');

    Route::post('/admin-roles', [AdminRoleController::class, 'store'])
        ->middleware('permission:saas-admin.admin_role.create')
        ->name('saas-admin.admin-roles.store');

    Route::put('/admin-roles/{id}', [AdminRoleController::class, 'update'])
        ->middleware('permission:saas-admin.admin_role.update')
        ->name('saas-admin.admin-roles.update');

    Route::delete('/admin-roles/{id}', [AdminRoleController::class, 'destroy'])
        ->middleware('permission:saas-admin.admin_role.delete')
        ->name('saas-admin.admin-roles.destroy');

    Route::post('/admin-roles/{id}/permissions', [AdminRoleController::class, 'assignPermissions'])
        ->middleware('permission:saas-admin.admin_role.update')
        ->name('saas-admin.admin-roles.assign-permissions');

    // System Settings
    Route::get('/system-settings', [SystemSettingController::class, 'index'])
        ->middleware('permission:saas-admin.system_setting.view')
        ->name('saas-admin.system-settings.index');

    Route::get('/system-settings/{id}', [SystemSettingController::class, 'show'])
        ->middleware('permission:saas-admin.system_setting.view')
        ->name('saas-admin.system-settings.show');

    Route::post('/system-settings', [SystemSettingController::class, 'upsert'])
        ->middleware('permission:saas-admin.system_setting.update')
        ->name('saas-admin.system-settings.upsert');

    Route::delete('/system-settings/{id}', [SystemSettingController::class, 'destroy'])
        ->middleware('permission:saas-admin.system_setting.delete')
        ->name('saas-admin.system-settings.destroy');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('saas-admin.notifications.index');

    Route::post('/notifications', [NotificationController::class, 'store'])
        ->name('saas-admin.notifications.store');

    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])
        ->name('saas-admin.notifications.mark-read');

    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])
        ->name('saas-admin.notifications.destroy');

    // Support Tickets
    Route::get('/support-tickets', [SupportTicketController::class, 'index'])
        ->name('saas-admin.support-tickets.index');

    Route::get('/support-tickets/{id}', [SupportTicketController::class, 'show'])
        ->name('saas-admin.support-tickets.show');

    Route::post('/support-tickets', [SupportTicketController::class, 'store'])
        ->name('saas-admin.support-tickets.store');

    Route::put('/support-tickets/{id}', [SupportTicketController::class, 'update'])
        ->name('saas-admin.support-tickets.update');

    Route::delete('/support-tickets/{id}', [SupportTicketController::class, 'destroy'])
        ->name('saas-admin.support-tickets.destroy');
});
