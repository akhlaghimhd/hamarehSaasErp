<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $demoTenantId = '3ab77cac-1343-4b13-8e14-0d887aad132a';

        $permissions = $this->getBasePermissions();

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

        $existingRole = DB::table('tenant_roles')
            ->where('tenant_id', $demoTenantId)
            ->where('code', 'tenant-admin')
            ->first();

        if ($existingRole) {
            $actualRoleId = $existingRole->tenant_role_id;

            DB::table('tenant_roles')
                ->where('tenant_role_id', $actualRoleId)
                ->update([
                    'name'        => 'Tenant Administrator',
                    'description' => 'Full access role for tenant administration',
                    'status'      => 1,
                    'updated_at'  => now(),
                ]);
        } else {
            $actualRoleId = (string) Str::uuid();

            DB::table('tenant_roles')->insert([
                'tenant_role_id' => $actualRoleId,
                'tenant_id'      => $demoTenantId,
                'code'           => 'tenant-admin',
                'name'           => 'Tenant Administrator',
                'description'    => 'Full access role for tenant administration',
                'status'         => 1,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        DB::table('tenant_role_permissions')
            ->where('tenant_id', $demoTenantId)
            ->where('tenant_role_id', $actualRoleId)
            ->delete();

        $insertData = [];
        foreach ($permissionIds as $permId) {
            $insertData[] = [
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id'                 => $demoTenantId,
                'tenant_role_id'            => $actualRoleId,
                'tenant_permission_id'      => $permId,
                'created_at'                => now(),
                'updated_at'                => now(),
            ];
        }

        if (!empty($insertData)) {
            DB::table('tenant_role_permissions')->insert($insertData);
        }
    }

    private function getBasePermissions(): array
    {
        return [
            ['code' => 'identity.user.view', 'name' => 'View Users', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.user.create', 'name' => 'Create User', 'module_name' => 'Identity', 'action_type' => 'CREATE'],
            ['code' => 'identity.user.update', 'name' => 'Update User', 'module_name' => 'Identity', 'action_type' => 'UPDATE'],
            ['code' => 'identity.user.delete', 'name' => 'Delete User', 'module_name' => 'Identity', 'action_type' => 'DELETE'],
            ['code' => 'procurement.purchase-order.view', 'name' => 'View Purchase Orders', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.purchase-order.create', 'name' => 'Create Purchase Order', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.sales-invoice.view', 'name' => 'View Sales Invoices', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.sales-invoice.create', 'name' => 'Create Sales Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.sales-invoice.update', 'name' => 'Update Sales Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'UPDATE'],
            ['code' => 'procurement.sales-invoice.post', 'name' => 'Post Sales Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],
            ['code' => 'procurement.purchase-invoice.view', 'name' => 'View Purchase Invoices', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.purchase-invoice.create', 'name' => 'Create Purchase Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.purchase-invoice.update', 'name' => 'Update Purchase Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'UPDATE'],
            ['code' => 'procurement.purchase-invoice.post', 'name' => 'Post Purchase Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],
        ];
    }
}
