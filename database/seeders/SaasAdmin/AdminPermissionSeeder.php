<?php

namespace Database\Seeders\SaasAdmin;

use App\Modules\SaasAdmin\Models\AdminPermission;
use App\Modules\SaasAdmin\Models\AdminRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * L2-D03 – Basic seed for Layer 2 admin permissions and a Super Admin role.
 */
class AdminPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Admin Users
            ['code' => 'saas-admin.admin_user.view',   'name' => 'View Admin Users',   'module_name' => 'SaasAdmin', 'permission_group' => 'AdminUser', 'action_type' => 'R'],
            ['code' => 'saas-admin.admin_user.create', 'name' => 'Create Admin User',  'module_name' => 'SaasAdmin', 'permission_group' => 'AdminUser', 'action_type' => 'C'],
            ['code' => 'saas-admin.admin_user.update', 'name' => 'Update Admin User',  'module_name' => 'SaasAdmin', 'permission_group' => 'AdminUser', 'action_type' => 'U'],
            ['code' => 'saas-admin.admin_user.delete', 'name' => 'Delete Admin User',  'module_name' => 'SaasAdmin', 'permission_group' => 'AdminUser', 'action_type' => 'D'],

            // Admin Roles
            ['code' => 'saas-admin.admin_role.view',   'name' => 'View Admin Roles',   'module_name' => 'SaasAdmin', 'permission_group' => 'AdminRole', 'action_type' => 'R'],
            ['code' => 'saas-admin.admin_role.create', 'name' => 'Create Admin Role',  'module_name' => 'SaasAdmin', 'permission_group' => 'AdminRole', 'action_type' => 'C'],
            ['code' => 'saas-admin.admin_role.update', 'name' => 'Update Admin Role',  'module_name' => 'SaasAdmin', 'permission_group' => 'AdminRole', 'action_type' => 'U'],
            ['code' => 'saas-admin.admin_role.delete', 'name' => 'Delete Admin Role',  'module_name' => 'SaasAdmin', 'permission_group' => 'AdminRole', 'action_type' => 'D'],

            // System Settings
            ['code' => 'saas-admin.system_setting.view',   'name' => 'View System Settings',   'module_name' => 'SaasAdmin', 'permission_group' => 'SystemSetting', 'action_type' => 'R'],
            ['code' => 'saas-admin.system_setting.update', 'name' => 'Update System Settings', 'module_name' => 'SaasAdmin', 'permission_group' => 'SystemSetting', 'action_type' => 'U'],
            ['code' => 'saas-admin.system_setting.delete', 'name' => 'Delete System Settings', 'module_name' => 'SaasAdmin', 'permission_group' => 'SystemSetting', 'action_type' => 'D'],

            // Audit Logs
            ['code' => 'saas-admin.audit_log.view', 'name' => 'View Audit Logs', 'module_name' => 'SaasAdmin', 'permission_group' => 'AuditLog', 'action_type' => 'R'],

            // Notifications
            ['code' => 'saas-admin.notification.view',   'name' => 'View Notifications',   'module_name' => 'SaasAdmin', 'permission_group' => 'Notification', 'action_type' => 'R'],
            ['code' => 'saas-admin.notification.create', 'name' => 'Create Notification',  'module_name' => 'SaasAdmin', 'permission_group' => 'Notification', 'action_type' => 'C'],
            ['code' => 'saas-admin.notification.update', 'name' => 'Update Notification',  'module_name' => 'SaasAdmin', 'permission_group' => 'Notification', 'action_type' => 'U'],
            ['code' => 'saas-admin.notification.delete', 'name' => 'Delete Notification',  'module_name' => 'SaasAdmin', 'permission_group' => 'Notification', 'action_type' => 'D'],

            // Support Tickets
            ['code' => 'saas-admin.support_ticket.view',   'name' => 'View Support Tickets',   'module_name' => 'SaasAdmin', 'permission_group' => 'SupportTicket', 'action_type' => 'R'],
            ['code' => 'saas-admin.support_ticket.create', 'name' => 'Create Support Ticket',  'module_name' => 'SaasAdmin', 'permission_group' => 'SupportTicket', 'action_type' => 'C'],
            ['code' => 'saas-admin.support_ticket.update', 'name' => 'Update Support Ticket',  'module_name' => 'SaasAdmin', 'permission_group' => 'SupportTicket', 'action_type' => 'U'],
            ['code' => 'saas-admin.support_ticket.delete', 'name' => 'Delete Support Ticket',  'module_name' => 'SaasAdmin', 'permission_group' => 'SupportTicket', 'action_type' => 'D'],

            // API Keys
            ['code' => 'saas-admin.api_key.view',   'name' => 'View API Keys',   'module_name' => 'SaasAdmin', 'permission_group' => 'ApiKey', 'action_type' => 'R'],
            ['code' => 'saas-admin.api_key.create', 'name' => 'Create API Key',  'module_name' => 'SaasAdmin', 'permission_group' => 'ApiKey', 'action_type' => 'C'],
            ['code' => 'saas-admin.api_key.delete', 'name' => 'Delete API Key',  'module_name' => 'SaasAdmin', 'permission_group' => 'ApiKey', 'action_type' => 'D'],

            // Webhooks
            ['code' => 'saas-admin.webhook.view',   'name' => 'View Webhooks',   'module_name' => 'SaasAdmin', 'permission_group' => 'Webhook', 'action_type' => 'R'],
            ['code' => 'saas-admin.webhook.create', 'name' => 'Create Webhook',  'module_name' => 'SaasAdmin', 'permission_group' => 'Webhook', 'action_type' => 'C'],
            ['code' => 'saas-admin.webhook.update', 'name' => 'Update Webhook',  'module_name' => 'SaasAdmin', 'permission_group' => 'Webhook', 'action_type' => 'U'],
            ['code' => 'saas-admin.webhook.delete', 'name' => 'Delete Webhook',  'module_name' => 'SaasAdmin', 'permission_group' => 'Webhook', 'action_type' => 'D'],
        ];

        $permissionIds = [];

        foreach ($permissions as $perm) {
            $model = AdminPermission::firstOrCreate(
                ['code' => $perm['code']],
                [
                    'admin_permission_id' => (string) Str::uuid(),
                    'name'                => $perm['name'],
                    'module_name'         => $perm['module_name'],
                    'permission_group'    => $perm['permission_group'],
                    'action_type'         => $perm['action_type'],
                    'description'         => $perm['name'],
                ]
            );
            $permissionIds[] = $model->admin_permission_id;
        }

        // Super Admin role
        $superRole = AdminRole::firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            [
                'admin_role_id' => (string) Str::uuid(),
                'name'          => 'Super Administrator',
                'description'   => 'Full access to all SaaS Admin features',
                'status'        => 1,
            ]
        );

        // Attach all permissions to Super Admin (idempotent)
        $superRole->permissions()->syncWithoutDetaching($permissionIds);
    }
}
