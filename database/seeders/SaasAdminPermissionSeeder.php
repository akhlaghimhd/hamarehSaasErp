<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds SaaS Admin (Layer 2) permission codes into tenant_permissions
 * for the demo tenant so route middleware permission:saas-admin.* works.
 */
class SaasAdminPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $demoTenantId = '3ab77cac-1343-4b13-8e14-0d887aad132a';

        $permissions = [
            // Admin User
            ['code' => 'saas-admin.admin_user.view', 'name' => 'View Admin Users', 'module_name' => 'SaasAdmin', 'action_type' => 'READ'],
            ['code' => 'saas-admin.admin_user.create', 'name' => 'Create Admin User', 'module_name' => 'SaasAdmin', 'action_type' => 'CREATE'],
            ['code' => 'saas-admin.admin_user.update', 'name' => 'Update Admin User', 'module_name' => 'SaasAdmin', 'action_type' => 'UPDATE'],
            ['code' => 'saas-admin.admin_user.delete', 'name' => 'Delete Admin User', 'module_name' => 'SaasAdmin', 'action_type' => 'DELETE'],

            // Admin Role
            ['code' => 'saas-admin.admin_role.view', 'name' => 'View Admin Roles', 'module_name' => 'SaasAdmin', 'action_type' => 'READ'],
            ['code' => 'saas-admin.admin_role.create', 'name' => 'Create Admin Role', 'module_name' => 'SaasAdmin', 'action_type' => 'CREATE'],
            ['code' => 'saas-admin.admin_role.update', 'name' => 'Update Admin Role', 'module_name' => 'SaasAdmin', 'action_type' => 'UPDATE'],
            ['code' => 'saas-admin.admin_role.delete', 'name' => 'Delete Admin Role', 'module_name' => 'SaasAdmin', 'action_type' => 'DELETE'],

            // System Setting
            ['code' => 'saas-admin.system_setting.view', 'name' => 'View System Settings', 'module_name' => 'SaasAdmin', 'action_type' => 'READ'],
            ['code' => 'saas-admin.system_setting.update', 'name' => 'Update System Settings', 'module_name' => 'SaasAdmin', 'action_type' => 'UPDATE'],
            ['code' => 'saas-admin.system_setting.delete', 'name' => 'Delete System Setting', 'module_name' => 'SaasAdmin', 'action_type' => 'DELETE'],

            // Audit Log
            ['code' => 'saas-admin.audit_log.view', 'name' => 'View Audit Logs', 'module_name' => 'SaasAdmin', 'action_type' => 'READ'],

            // Notification
            ['code' => 'saas-admin.notification.view', 'name' => 'View Notifications', 'module_name' => 'SaasAdmin', 'action_type' => 'READ'],
            ['code' => 'saas-admin.notification.create', 'name' => 'Create Notification', 'module_name' => 'SaasAdmin', 'action_type' => 'CREATE'],
            ['code' => 'saas-admin.notification.update', 'name' => 'Update Notification', 'module_name' => 'SaasAdmin', 'action_type' => 'UPDATE'],
            ['code' => 'saas-admin.notification.delete', 'name' => 'Delete Notification', 'module_name' => 'SaasAdmin', 'action_type' => 'DELETE'],

            // Support Ticket
            ['code' => 'saas-admin.support_ticket.view', 'name' => 'View Support Tickets', 'module_name' => 'SaasAdmin', 'action_type' => 'READ'],
            ['code' => 'saas-admin.support_ticket.create', 'name' => 'Create Support Ticket', 'module_name' => 'SaasAdmin', 'action_type' => 'CREATE'],
            ['code' => 'saas-admin.support_ticket.update', 'name' => 'Update Support Ticket', 'module_name' => 'SaasAdmin', 'action_type' => 'UPDATE'],
            ['code' => 'saas-admin.support_ticket.delete', 'name' => 'Delete Support Ticket', 'module_name' => 'SaasAdmin', 'action_type' => 'DELETE'],

            // API Key
            ['code' => 'saas-admin.api_key.view', 'name' => 'View API Keys', 'module_name' => 'SaasAdmin', 'action_type' => 'READ'],
            ['code' => 'saas-admin.api_key.create', 'name' => 'Create API Key', 'module_name' => 'SaasAdmin', 'action_type' => 'CREATE'],
            ['code' => 'saas-admin.api_key.delete', 'name' => 'Revoke API Key', 'module_name' => 'SaasAdmin', 'action_type' => 'DELETE'],

            // Webhook
            ['code' => 'saas-admin.webhook.view', 'name' => 'View Webhooks', 'module_name' => 'SaasAdmin', 'action_type' => 'READ'],
            ['code' => 'saas-admin.webhook.create', 'name' => 'Create Webhook', 'module_name' => 'SaasAdmin', 'action_type' => 'CREATE'],
            ['code' => 'saas-admin.webhook.update', 'name' => 'Update Webhook', 'module_name' => 'SaasAdmin', 'action_type' => 'UPDATE'],
            ['code' => 'saas-admin.webhook.delete', 'name' => 'Delete Webhook', 'module_name' => 'SaasAdmin', 'action_type' => 'DELETE'],
        ];

        $permissionIds = [];

        foreach ($permissions as $perm) {
            $existing = DB::table('tenant_permissions')
                ->where('tenant_id', $demoTenantId)
                ->where('code', $perm['code'])
                ->first();

            if ($existing) {
                DB::table('tenant_permissions')
                    ->where('tenant_permission_id', $existing->tenant_permission_id)
                    ->update([
                        'name'        => $perm['name'],
                        'module_name' => $perm['module_name'],
                        'action_type' => $perm['action_type'],
                        'status'      => 1,
                        'updated_at'  => now(),
                    ]);
                $permissionIds[] = $existing->tenant_permission_id;
            } else {
                $permissionId = (string) Str::uuid();
                DB::table('tenant_permissions')->insert([
                    'tenant_permission_id' => $permissionId,
                    'tenant_id'            => $demoTenantId,
                    'code'                 => $perm['code'],
                    'name'                 => $perm['name'],
                    'module_name'          => $perm['module_name'],
                    'action_type'          => $perm['action_type'],
                    'status'               => 1,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
                $permissionIds[] = $permissionId;
            }
        }

        $role = DB::table('tenant_roles')
            ->where('tenant_id', $demoTenantId)
            ->where('code', 'tenant-admin')
            ->first();

        if ($role && !empty($permissionIds)) {
            foreach ($permissionIds as $permId) {
                $exists = DB::table('tenant_role_permissions')
                    ->where('tenant_id', $demoTenantId)
                    ->where('tenant_role_id', $role->tenant_role_id)
                    ->where('tenant_permission_id', $permId)
                    ->exists();

                if (!$exists) {
                    DB::table('tenant_role_permissions')->insert([
                        'tenant_role_permission_id' => (string) Str::uuid(),
                        'tenant_id'                 => $demoTenantId,
                        'tenant_role_id'            => $role->tenant_role_id,
                        'tenant_permission_id'      => $permId,
                        'created_at'                => now(),
                        'updated_at'                => now(),
                    ]);
                }
            }
        }
    }
}
