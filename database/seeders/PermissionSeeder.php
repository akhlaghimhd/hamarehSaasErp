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
            // Identity
            ['code' => 'identity.user.view', 'name' => 'View Users', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.user.create', 'name' => 'Create User', 'module_name' => 'Identity', 'action_type' => 'CREATE'],
            ['code' => 'identity.user.update', 'name' => 'Update User', 'module_name' => 'Identity', 'action_type' => 'UPDATE'],
            ['code' => 'identity.user.delete', 'name' => 'Delete User', 'module_name' => 'Identity', 'action_type' => 'DELETE'],
            ['code' => 'identity.role.view', 'name' => 'View Roles', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.role.create', 'name' => 'Create Role', 'module_name' => 'Identity', 'action_type' => 'CREATE'],
            ['code' => 'identity.role.update', 'name' => 'Update Role', 'module_name' => 'Identity', 'action_type' => 'UPDATE'],
            ['code' => 'identity.role.delete', 'name' => 'Delete Role', 'module_name' => 'Identity', 'action_type' => 'DELETE'],
            ['code' => 'identity.permission.view', 'name' => 'View Permissions', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.permission.assign', 'name' => 'Assign Permission', 'module_name' => 'Identity', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.scope.view', 'name' => 'View Scopes', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.scope.assign', 'name' => 'Assign Scope to User', 'module_name' => 'Identity', 'action_type' => 'EXECUTE'],

            // Organization
            ['code' => 'organization.company.view', 'name' => 'View Companies', 'module_name' => 'Organization', 'action_type' => 'READ'],
            ['code' => 'organization.company.create', 'name' => 'Create Company', 'module_name' => 'Organization', 'action_type' => 'CREATE'],
            ['code' => 'organization.company.update', 'name' => 'Update Company', 'module_name' => 'Organization', 'action_type' => 'UPDATE'],
            ['code' => 'organization.company.delete', 'name' => 'Delete Company', 'module_name' => 'Organization', 'action_type' => 'DELETE'],
            ['code' => 'organization.branch.view', 'name' => 'View Branches', 'module_name' => 'Organization', 'action_type' => 'READ'],
            ['code' => 'organization.branch.create', 'name' => 'Create Branch', 'module_name' => 'Organization', 'action_type' => 'CREATE'],
            ['code' => 'organization.branch.update', 'name' => 'Update Branch', 'module_name' => 'Organization', 'action_type' => 'UPDATE'],
            ['code' => 'organization.branch.delete', 'name' => 'Delete Branch', 'module_name' => 'Organization', 'action_type' => 'DELETE'],
            ['code' => 'organization.department.view', 'name' => 'View Departments', 'module_name' => 'Organization', 'action_type' => 'READ'],
            ['code' => 'organization.department.create', 'name' => 'Create Department', 'module_name' => 'Organization', 'action_type' => 'CREATE'],
            ['code' => 'organization.department.update', 'name' => 'Update Department', 'module_name' => 'Organization', 'action_type' => 'UPDATE'],
            ['code' => 'organization.department.delete', 'name' => 'Delete Department', 'module_name' => 'Organization', 'action_type' => 'DELETE'],

            // MasterData — Business Partner
            ['code' => 'master-data.business-partner.view', 'name' => 'View Business Partners', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.business-partner.create', 'name' => 'Create Business Partner', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.business-partner.update', 'name' => 'Update Business Partner', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.business-partner.delete', 'name' => 'Delete Business Partner', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],

            // Inventory-owned Item / Warehouse (codes keep master-data prefix for backward compatibility)
            ['code' => 'master-data.item.view', 'name' => 'View Items', 'module_name' => 'Inventory', 'action_type' => 'READ'],
            ['code' => 'master-data.item.create', 'name' => 'Create Item', 'module_name' => 'Inventory', 'action_type' => 'CREATE'],
            ['code' => 'master-data.item.update', 'name' => 'Update Item', 'module_name' => 'Inventory', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.item.delete', 'name' => 'Delete Item', 'module_name' => 'Inventory', 'action_type' => 'DELETE'],
            ['code' => 'master-data.warehouse.view', 'name' => 'View Warehouses', 'module_name' => 'Inventory', 'action_type' => 'READ'],
            ['code' => 'master-data.warehouse.create', 'name' => 'Create Warehouse', 'module_name' => 'Inventory', 'action_type' => 'CREATE'],
            ['code' => 'master-data.warehouse.update', 'name' => 'Update Warehouse', 'module_name' => 'Inventory', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.warehouse.delete', 'name' => 'Delete Warehouse', 'module_name' => 'Inventory', 'action_type' => 'DELETE'],

            // Inventory — Location
            ['code' => 'inventory.location.view', 'name' => 'View Locations', 'module_name' => 'Inventory', 'action_type' => 'READ'],
            ['code' => 'inventory.location.create', 'name' => 'Create Location', 'module_name' => 'Inventory', 'action_type' => 'CREATE'],
            ['code' => 'inventory.location.update', 'name' => 'Update Location', 'module_name' => 'Inventory', 'action_type' => 'UPDATE'],
            ['code' => 'inventory.location.delete', 'name' => 'Delete Location', 'module_name' => 'Inventory', 'action_type' => 'DELETE'],

            // Inventory — Stock Batch
            ['code' => 'inventory.stock-batch.view', 'name' => 'View Stock Batches', 'module_name' => 'Inventory', 'action_type' => 'READ'],
            ['code' => 'inventory.stock-batch.create', 'name' => 'Create Stock Batch', 'module_name' => 'Inventory', 'action_type' => 'CREATE'],
            ['code' => 'inventory.stock-batch.update', 'name' => 'Update Stock Batch', 'module_name' => 'Inventory', 'action_type' => 'UPDATE'],
            ['code' => 'inventory.stock-batch.delete', 'name' => 'Delete Stock Batch', 'module_name' => 'Inventory', 'action_type' => 'DELETE'],

            // Inventory — Stock Balance (read-only)
            ['code' => 'inventory.stock-balance.view', 'name' => 'View Stock Balances', 'module_name' => 'Inventory', 'action_type' => 'READ'],
            ['code' => 'inventory.stock-reservation.reserve', 'name' => 'Reserve Stock', 'module_name' => 'Inventory', 'action_type' => 'CREATE'],
            ['code' => 'inventory.stock-reservation.release', 'name' => 'Release Stock Reservation', 'module_name' => 'Inventory', 'action_type' => 'UPDATE'],

            // Inventory — Document
            ['code' => 'inventory.document.view', 'name' => 'View Inventory Documents', 'module_name' => 'Inventory', 'action_type' => 'READ'],
            ['code' => 'inventory.document.create', 'name' => 'Create Inventory Document', 'module_name' => 'Inventory', 'action_type' => 'CREATE'],
            ['code' => 'inventory.document.update', 'name' => 'Update Inventory Document', 'module_name' => 'Inventory', 'action_type' => 'UPDATE'],
            ['code' => 'inventory.document.delete', 'name' => 'Delete Inventory Document', 'module_name' => 'Inventory', 'action_type' => 'DELETE'],
            ['code' => 'inventory.document.post', 'name' => 'Post Inventory Document', 'module_name' => 'Inventory', 'action_type' => 'EXECUTE'],
            ['code' => 'inventory.document.void', 'name' => 'Void Inventory Document', 'module_name' => 'Inventory', 'action_type' => 'EXECUTE'],

            // Inventory — Document Item
            ['code' => 'inventory.document-item.view', 'name' => 'View Inventory Document Items', 'module_name' => 'Inventory', 'action_type' => 'READ'],
            ['code' => 'inventory.document-item.create', 'name' => 'Create Inventory Document Item', 'module_name' => 'Inventory', 'action_type' => 'CREATE'],
            ['code' => 'inventory.document-item.update', 'name' => 'Update Inventory Document Item', 'module_name' => 'Inventory', 'action_type' => 'UPDATE'],
            ['code' => 'inventory.document-item.delete', 'name' => 'Delete Inventory Document Item', 'module_name' => 'Inventory', 'action_type' => 'DELETE'],

            // MasterData — Cost Center
            ['code' => 'master-data.cost-center.view', 'name' => 'View Cost Centers', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.cost-center.create', 'name' => 'Create Cost Center', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.cost-center.update', 'name' => 'Update Cost Center', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.cost-center.delete', 'name' => 'Delete Cost Center', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],

            // MasterData — Currency / Country / UoM / Bank
            ['code' => 'master-data.currency.view', 'name' => 'View Currencies', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.currency.create', 'name' => 'Create Currency', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.currency.update', 'name' => 'Update Currency', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.currency.delete', 'name' => 'Delete Currency', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],
            ['code' => 'master-data.country.view', 'name' => 'View Countries', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.country.create', 'name' => 'Create Country', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.country.update', 'name' => 'Update Country', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.country.delete', 'name' => 'Delete Country', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],
            ['code' => 'master-data.unit-of-measure.view', 'name' => 'View Units of Measure', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.unit-of-measure.create', 'name' => 'Create Unit of Measure', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.unit-of-measure.update', 'name' => 'Update Unit of Measure', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.unit-of-measure.delete', 'name' => 'Delete Unit of Measure', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],
            ['code' => 'master-data.bank-account.view', 'name' => 'View Bank Accounts', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.bank-account.create', 'name' => 'Create Bank Account', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.bank-account.update', 'name' => 'Update Bank Account', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.bank-account.delete', 'name' => 'Delete Bank Account', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],

            // Accounting — Fiscal Period
            ['code' => 'accounting.fiscal-period.view', 'name' => 'View Fiscal Periods', 'module_name' => 'Accounting', 'action_type' => 'READ'],
            ['code' => 'accounting.fiscal-period.create', 'name' => 'Create Fiscal Period', 'module_name' => 'Accounting', 'action_type' => 'CREATE'],
            ['code' => 'accounting.fiscal-period.update', 'name' => 'Update Fiscal Period', 'module_name' => 'Accounting', 'action_type' => 'UPDATE'],
            ['code' => 'accounting.fiscal-period.delete', 'name' => 'Delete Fiscal Period', 'module_name' => 'Accounting', 'action_type' => 'DELETE'],

            // Accounting — Account
            ['code' => 'accounting.account.view', 'name' => 'View Accounts', 'module_name' => 'Accounting', 'action_type' => 'READ'],
            ['code' => 'accounting.account.create', 'name' => 'Create Account', 'module_name' => 'Accounting', 'action_type' => 'CREATE'],
            ['code' => 'accounting.account.update', 'name' => 'Update Account', 'module_name' => 'Accounting', 'action_type' => 'UPDATE'],
            ['code' => 'accounting.account.delete', 'name' => 'Delete Account', 'module_name' => 'Accounting', 'action_type' => 'DELETE'],

            // Accounting — Voucher (L6-ACC-02.3)
            ['code' => 'accounting.voucher.view', 'name' => 'View Vouchers', 'module_name' => 'Accounting', 'action_type' => 'READ'],
            ['code' => 'accounting.voucher.create', 'name' => 'Create Voucher', 'module_name' => 'Accounting', 'action_type' => 'CREATE'],
            ['code' => 'accounting.voucher.update', 'name' => 'Update Voucher', 'module_name' => 'Accounting', 'action_type' => 'UPDATE'],
            ['code' => 'accounting.voucher.post', 'name' => 'Post Voucher', 'module_name' => 'Accounting', 'action_type' => 'EXECUTE'],
            ['code' => 'accounting.voucher.delete', 'name' => 'Delete Voucher', 'module_name' => 'Accounting', 'action_type' => 'DELETE'],

            // Accounting — Voucher Item / Tax (L6-ACC-02.4)
            ['code' => 'accounting.voucher-item.view', 'name' => 'View Voucher Items', 'module_name' => 'Accounting', 'action_type' => 'READ'],
            ['code' => 'accounting.voucher-item.create', 'name' => 'Create Voucher Item', 'module_name' => 'Accounting', 'action_type' => 'CREATE'],
            ['code' => 'accounting.voucher-item.update', 'name' => 'Update Voucher Item', 'module_name' => 'Accounting', 'action_type' => 'UPDATE'],
            ['code' => 'accounting.voucher-item.delete', 'name' => 'Delete Voucher Item', 'module_name' => 'Accounting', 'action_type' => 'DELETE'],
            ['code' => 'accounting.tax-transaction.view', 'name' => 'View Tax Transactions', 'module_name' => 'Accounting', 'action_type' => 'READ'],
            ['code' => 'accounting.tax-transaction.create', 'name' => 'Create Tax Transaction', 'module_name' => 'Accounting', 'action_type' => 'CREATE'],
            ['code' => 'accounting.tax-transaction.update', 'name' => 'Update Tax Transaction', 'module_name' => 'Accounting', 'action_type' => 'UPDATE'],
            ['code' => 'accounting.tax-transaction.delete', 'name' => 'Delete Tax Transaction', 'module_name' => 'Accounting', 'action_type' => 'DELETE'],
        ];
    }
}
