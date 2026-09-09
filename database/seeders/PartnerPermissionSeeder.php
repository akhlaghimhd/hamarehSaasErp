<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * L3-P3-01 – Seeds PartnerLayer (Layer 3) permission codes into tenant_permissions
 * for the demo tenant so route middleware permission:partner.* works.
 */
class PartnerPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $demoTenantId = '3ab77cac-1343-4b13-8e14-0d887aad132a';

        $permissions = $this->getPartnerPermissions();

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
                        'action_type' => $perm['action_type'] ?? null,
                        'description' => $perm['description'] ?? null,
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
                    'action_type'          => $perm['action_type'] ?? null,
                    'description'          => $perm['description'] ?? null,
                    'status'               => 1,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
                $permissionIds[] = $permissionId;
            }
        }

        // Attach to tenant-admin role if present
        $existingRole = DB::table('tenant_roles')
            ->where('tenant_id', $demoTenantId)
            ->where('code', 'tenant-admin')
            ->first();

        if ($existingRole && !empty($permissionIds)) {
            $actualRoleId = $existingRole->tenant_role_id;
            $insertData = [];
            foreach ($permissionIds as $permId) {
                $exists = DB::table('tenant_role_permissions')
                    ->where('tenant_role_id', $actualRoleId)
                    ->where('tenant_permission_id', $permId)
                    ->exists();
                if (!$exists) {
                    $insertData[] = [
                        'tenant_role_permission_id' => (string) Str::uuid(),
                        'tenant_id'                 => $demoTenantId,
                        'tenant_role_id'            => $actualRoleId,
                        'tenant_permission_id'      => $permId,
                        'created_at'                => now(),
                        'updated_at'                => now(),
                    ];
                }
            }
            if (!empty($insertData)) {
                DB::table('tenant_role_permissions')->insert($insertData);
            }
        }
    }

    private function getPartnerPermissions(): array
    {
        $module = 'PartnerLayer';
        return [
            // Partner core
            ['code' => 'partner.partner.view',   'name' => 'View Partners',   'module_name' => $module, 'action_type' => 'READ'],
            ['code' => 'partner.partner.create', 'name' => 'Create Partner',  'module_name' => $module, 'action_type' => 'CREATE'],
            ['code' => 'partner.partner.update', 'name' => 'Update Partner',  'module_name' => $module, 'action_type' => 'UPDATE'],
            ['code' => 'partner.partner.delete', 'name' => 'Delete Partner',  'module_name' => $module, 'action_type' => 'DELETE'],

            // Partner User
            ['code' => 'partner.partner_user.view',   'name' => 'View Partner Users',   'module_name' => $module, 'action_type' => 'READ'],
            ['code' => 'partner.partner_user.create', 'name' => 'Create Partner User',  'module_name' => $module, 'action_type' => 'CREATE'],
            ['code' => 'partner.partner_user.update', 'name' => 'Update Partner User',  'module_name' => $module, 'action_type' => 'UPDATE'],
            ['code' => 'partner.partner_user.delete', 'name' => 'Delete Partner User',  'module_name' => $module, 'action_type' => 'DELETE'],

            // Tenant Assignment
            ['code' => 'partner.assignment.view',   'name' => 'View Partner Tenant Assignments',   'module_name' => $module, 'action_type' => 'READ'],
            ['code' => 'partner.assignment.create', 'name' => 'Create Partner Tenant Assignment', 'module_name' => $module, 'action_type' => 'CREATE'],
            ['code' => 'partner.assignment.update', 'name' => 'Update Partner Tenant Assignment', 'module_name' => $module, 'action_type' => 'UPDATE'],
            ['code' => 'partner.assignment.delete', 'name' => 'Delete Partner Tenant Assignment', 'module_name' => $module, 'action_type' => 'DELETE'],

            // Agreement
            ['code' => 'partner.agreement.view',   'name' => 'View Partner Agreements',   'module_name' => $module, 'action_type' => 'READ'],
            ['code' => 'partner.agreement.create', 'name' => 'Create Partner Agreement',  'module_name' => $module, 'action_type' => 'CREATE'],
            ['code' => 'partner.agreement.update', 'name' => 'Update Partner Agreement',  'module_name' => $module, 'action_type' => 'UPDATE'],
            ['code' => 'partner.agreement.delete', 'name' => 'Delete Partner Agreement',  'module_name' => $module, 'action_type' => 'DELETE'],

            // Commission Rule
            ['code' => 'partner.commission_rule.view',   'name' => 'View Partner Commission Rules',   'module_name' => $module, 'action_type' => 'READ'],
            ['code' => 'partner.commission_rule.create', 'name' => 'Create Partner Commission Rule',  'module_name' => $module, 'action_type' => 'CREATE'],
            ['code' => 'partner.commission_rule.update', 'name' => 'Update Partner Commission Rule',  'module_name' => $module, 'action_type' => 'UPDATE'],
            ['code' => 'partner.commission_rule.delete', 'name' => 'Delete Partner Commission Rule',  'module_name' => $module, 'action_type' => 'DELETE'],

            // Commission
            ['code' => 'partner.commission.view',   'name' => 'View Partner Commissions',   'module_name' => $module, 'action_type' => 'READ'],
            ['code' => 'partner.commission.create', 'name' => 'Create Partner Commission',  'module_name' => $module, 'action_type' => 'CREATE'],
            ['code' => 'partner.commission.update', 'name' => 'Update Partner Commission',  'module_name' => $module, 'action_type' => 'UPDATE'],
            ['code' => 'partner.commission.delete', 'name' => 'Delete Partner Commission',  'module_name' => $module, 'action_type' => 'DELETE'],

            // Payout
            ['code' => 'partner.payout.view',   'name' => 'View Partner Payouts',   'module_name' => $module, 'action_type' => 'READ'],
            ['code' => 'partner.payout.create', 'name' => 'Create Partner Payout',  'module_name' => $module, 'action_type' => 'CREATE'],
            ['code' => 'partner.payout.update', 'name' => 'Update Partner Payout',  'module_name' => $module, 'action_type' => 'UPDATE'],
            ['code' => 'partner.payout.delete', 'name' => 'Delete Partner Payout',  'module_name' => $module, 'action_type' => 'DELETE'],

            // Contact
            ['code' => 'partner.contact.view',   'name' => 'View Partner Contacts',   'module_name' => $module, 'action_type' => 'READ'],
            ['code' => 'partner.contact.create', 'name' => 'Create Partner Contact',  'module_name' => $module, 'action_type' => 'CREATE'],
            ['code' => 'partner.contact.update', 'name' => 'Update Partner Contact',  'module_name' => $module, 'action_type' => 'UPDATE'],
            ['code' => 'partner.contact.delete', 'name' => 'Delete Partner Contact',  'module_name' => $module, 'action_type' => 'DELETE'],

            // Document
            ['code' => 'partner.document.view',   'name' => 'View Partner Documents',   'module_name' => $module, 'action_type' => 'READ'],
            ['code' => 'partner.document.create', 'name' => 'Create Partner Document',  'module_name' => $module, 'action_type' => 'CREATE'],
            ['code' => 'partner.document.update', 'name' => 'Update Partner Document',  'module_name' => $module, 'action_type' => 'UPDATE'],
            ['code' => 'partner.document.delete', 'name' => 'Delete Partner Document',  'module_name' => $module, 'action_type' => 'DELETE'],

            // Bank Account
            ['code' => 'partner.bank_account.view',   'name' => 'View Partner Bank Accounts',   'module_name' => $module, 'action_type' => 'READ'],
            ['code' => 'partner.bank_account.create', 'name' => 'Create Partner Bank Account',  'module_name' => $module, 'action_type' => 'CREATE'],
            ['code' => 'partner.bank_account.update', 'name' => 'Update Partner Bank Account',  'module_name' => $module, 'action_type' => 'UPDATE'],
            ['code' => 'partner.bank_account.delete', 'name' => 'Delete Partner Bank Account',  'module_name' => $module, 'action_type' => 'DELETE'],

            // Activity Log
            ['code' => 'partner.activity_log.view',   'name' => 'View Partner Activity Logs',   'module_name' => $module, 'action_type' => 'READ'],
            ['code' => 'partner.activity_log.create', 'name' => 'Create Partner Activity Log',  'module_name' => $module, 'action_type' => 'CREATE'],
        ];
    }
}
