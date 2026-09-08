<?php

use Illuminate\Support\Facades\Route;
use App\Modules\SaasAdmin\Controllers\AdminUserController;
use App\Modules\SaasAdmin\Controllers\AdminRoleController;
use App\Modules\SaasAdmin\Controllers\SystemSettingController;
use App\Modules\SaasAdmin\Controllers\NotificationController;
use App\Modules\SaasAdmin\Controllers\NotificationTemplateController;
use App\Modules\SaasAdmin\Controllers\SupportTicketController;
use App\Modules\SaasAdmin\Controllers\SupportTicketMessageController;
use App\Modules\SaasAdmin\Controllers\AuditLogController;
use App\Modules\SaasAdmin\Controllers\AdminApiKeyController;
use App\Modules\SaasAdmin\Controllers\AdminWebhookController;
use App\Modules\SaasAdmin\Controllers\AdminAuthController;

/*
|--------------------------------------------------------------------------
| Saas Admin API Routes (Layer 2 - Platform Administration)
|--------------------------------------------------------------------------
| Prefix: /api/saas-admin
*/

// Public admin auth (no sanctum)
Route::post('/auth/login', [AdminAuthController::class, 'login'])
    ->name('saas-admin.auth.login');

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/auth/logout', [AdminAuthController::class, 'logout'])
        ->name('saas-admin.auth.logout');

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

    // Audit Logs
    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:saas-admin.audit_log.view')
        ->name('saas-admin.audit-logs.index');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->middleware('permission:saas-admin.notification.view')
        ->name('saas-admin.notifications.index');
    Route::post('/notifications', [NotificationController::class, 'store'])
        ->middleware('permission:saas-admin.notification.create')
        ->name('saas-admin.notifications.store');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])
        ->middleware('permission:saas-admin.notification.update')
        ->name('saas-admin.notifications.mark-read');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])
        ->middleware('permission:saas-admin.notification.delete')
        ->name('saas-admin.notifications.destroy');

    // Notification Templates
    Route::get('/notification-templates', [NotificationTemplateController::class, 'index'])
        ->middleware('permission:saas-admin.notification.view')
        ->name('saas-admin.notification-templates.index');
    Route::post('/notification-templates', [NotificationTemplateController::class, 'upsert'])
        ->middleware('permission:saas-admin.notification.create')
        ->name('saas-admin.notification-templates.upsert');
    Route::delete('/notification-templates/{id}', [NotificationTemplateController::class, 'destroy'])
        ->middleware('permission:saas-admin.notification.delete')
        ->name('saas-admin.notification-templates.destroy');

    // Support Tickets
    Route::get('/support-tickets', [SupportTicketController::class, 'index'])
        ->middleware('permission:saas-admin.support_ticket.view')
        ->name('saas-admin.support-tickets.index');
    Route::get('/support-tickets/{id}', [SupportTicketController::class, 'show'])
        ->middleware('permission:saas-admin.support_ticket.view')
        ->name('saas-admin.support-tickets.show');
    Route::post('/support-tickets', [SupportTicketController::class, 'store'])
        ->middleware('permission:saas-admin.support_ticket.create')
        ->name('saas-admin.support-tickets.store');
    Route::put('/support-tickets/{id}', [SupportTicketController::class, 'update'])
        ->middleware('permission:saas-admin.support_ticket.update')
        ->name('saas-admin.support-tickets.update');
    Route::delete('/support-tickets/{id}', [SupportTicketController::class, 'destroy'])
        ->middleware('permission:saas-admin.support_ticket.delete')
        ->name('saas-admin.support-tickets.destroy');

    // Support Ticket Messages / Attachments
    Route::get('/support-tickets/{ticketId}/messages', [SupportTicketMessageController::class, 'index'])
        ->middleware('permission:saas-admin.support_ticket.view')
        ->name('saas-admin.support-ticket-messages.index');
    Route::post('/support-tickets/{ticketId}/messages', [SupportTicketMessageController::class, 'store'])
        ->middleware('permission:saas-admin.support_ticket.update')
        ->name('saas-admin.support-ticket-messages.store');
    Route::post('/support-ticket-messages/{messageId}/attachments', [SupportTicketMessageController::class, 'storeAttachment'])
        ->middleware('permission:saas-admin.support_ticket.update')
        ->name('saas-admin.support-ticket-attachments.store');

    // API Keys
    Route::get('/api-keys', [AdminApiKeyController::class, 'index'])
        ->middleware('permission:saas-admin.api_key.view')
        ->name('saas-admin.api-keys.index');
    Route::post('/api-keys', [AdminApiKeyController::class, 'store'])
        ->middleware('permission:saas-admin.api_key.create')
        ->name('saas-admin.api-keys.store');
    Route::delete('/api-keys/{id}', [AdminApiKeyController::class, 'destroy'])
        ->middleware('permission:saas-admin.api_key.delete')
        ->name('saas-admin.api-keys.destroy');

    // Webhooks
    Route::get('/webhooks', [AdminWebhookController::class, 'index'])
        ->middleware('permission:saas-admin.webhook.view')
        ->name('saas-admin.webhooks.index');
    Route::post('/webhooks', [AdminWebhookController::class, 'store'])
        ->middleware('permission:saas-admin.webhook.create')
        ->name('saas-admin.webhooks.store');
    Route::put('/webhooks/{id}', [AdminWebhookController::class, 'update'])
        ->middleware('permission:saas-admin.webhook.update')
        ->name('saas-admin.webhooks.update');
    Route::delete('/webhooks/{id}', [AdminWebhookController::class, 'destroy'])
        ->middleware('permission:saas-admin.webhook.delete')
        ->name('saas-admin.webhooks.destroy');
});
