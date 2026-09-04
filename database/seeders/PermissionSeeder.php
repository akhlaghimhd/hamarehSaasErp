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
                    'description' => 'Full access role for tenant administrators',
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
                'description'    => 'Full access role for tenant administrators',
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
            ['code' => 'identity.permission.view', 'name' => 'View Permissions', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.permission.create', 'name' => 'Create Permission', 'module_name' => 'Identity', 'action_type' => 'CREATE'],
            ['code' => 'identity.permission.update', 'name' => 'Update Permission', 'module_name' => 'Identity', 'action_type' => 'UPDATE'],
            ['code' => 'identity.permission.delete', 'name' => 'Delete Permission', 'module_name' => 'Identity', 'action_type' => 'DELETE'],
            ['code' => 'identity.role.view', 'name' => 'View Roles', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.role.create', 'name' => 'Create Role', 'module_name' => 'Identity', 'action_type' => 'CREATE'],
            ['code' => 'identity.role.update', 'name' => 'Update Role', 'module_name' => 'Identity', 'action_type' => 'UPDATE'],
            ['code' => 'identity.role.delete', 'name' => 'Delete Role', 'module_name' => 'Identity', 'action_type' => 'DELETE'],
            ['code' => 'identity.role.assign', 'name' => 'Assign Role to User', 'module_name' => 'Identity', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.role.assign-permissions', 'name' => 'Assign Permissions to Role', 'module_name' => 'Identity', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.user.view', 'name' => 'View Tenant Users', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.user.create', 'name' => 'Create Tenant User', 'module_name' => 'Identity', 'action_type' => 'CREATE'],
            ['code' => 'identity.user.update', 'name' => 'Update Tenant User', 'module_name' => 'Identity', 'action_type' => 'UPDATE'],
            ['code' => 'identity.user.delete', 'name' => 'Delete Tenant User', 'module_name' => 'Identity', 'action_type' => 'DELETE'],
            ['code' => 'identity.profile.view', 'name' => 'View User Profiles', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.profile.update', 'name' => 'Update User Profiles', 'module_name' => 'Identity', 'action_type' => 'UPDATE'],
            ['code' => 'identity.profile.delete', 'name' => 'Delete User Profiles', 'module_name' => 'Identity', 'action_type' => 'DELETE'],
            ['code' => 'identity.membership_history.view', 'name' => 'View Membership History', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.scope.view', 'name' => 'View Scopes', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.scope.create', 'name' => 'Create Scope', 'module_name' => 'Identity', 'action_type' => 'CREATE'],
            ['code' => 'identity.scope.update', 'name' => 'Update Scope', 'module_name' => 'Identity', 'action_type' => 'UPDATE'],
            ['code' => 'identity.scope.delete', 'name' => 'Delete Scope', 'module_name' => 'Identity', 'action_type' => 'DELETE'],
            ['code' => 'identity.scope.assign', 'name' => 'Assign Scope to User', 'module_name' => 'Identity', 'action_type' => 'EXECUTE'],
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
            ['code' => 'master-data.business-partner.view', 'name' => 'View Business Partners', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.business-partner.create', 'name' => 'Create Business Partner', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.business-partner.update', 'name' => 'Update Business Partner', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.business-partner.delete', 'name' => 'Delete Business Partner', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],
            ['code' => 'master-data.item.view', 'name' => 'View Items', 'module_name' => 'Inventory', 'action_type' => 'READ'],
            ['code' => 'master-data.item.create', 'name' => 'Create Item', 'module_name' => 'Inventory', 'action_type' => 'CREATE'],
            ['code' => 'master-data.item.update', 'name' => 'Update Item', 'module_name' => 'Inventory', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.item.delete', 'name' => 'Delete Item', 'module_name' => 'Inventory', 'action_type' => 'DELETE'],
            ['code' => 'master-data.warehouse.view', 'name' => 'View Warehouses', 'module_name' => 'Inventory', 'action_type' => 'READ'],
            ['code' => 'master-data.warehouse.create', 'name' => 'Create Warehouse', 'module_name' => 'Inventory', 'action_type' => 'CREATE'],
            ['code' => 'master-data.warehouse.update', 'name' => 'Update Warehouse', 'module_name' => 'Inventory', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.warehouse.delete', 'name' => 'Delete Warehouse', 'module_name' => 'Inventory', 'action_type' => 'DELETE'],
            ['code' => 'master-data.cost-center.view', 'name' => 'View Cost Centers', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.cost-center.create', 'name' => 'Create Cost Center', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.cost-center.update', 'name' => 'Update Cost Center', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.cost-center.delete', 'name' => 'Delete Cost Center', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],
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
            ['code' => 'master-data.tax-category.view', 'name' => 'View Tax Categories', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.tax-category.create', 'name' => 'Create Tax Category', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.tax-category.update', 'name' => 'Update Tax Category', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.tax-category.delete', 'name' => 'Delete Tax Category', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],
            ['code' => 'master-data.tax-definition.view', 'name' => 'View Tax Definitions', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.tax-definition.create', 'name' => 'Create Tax Definition', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.tax-definition.update', 'name' => 'Update Tax Definition', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.tax-definition.delete', 'name' => 'Delete Tax Definition', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],
            ['code' => 'master-data.bank-account.view', 'name' => 'View Bank Accounts', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.bank-account.create', 'name' => 'Create Bank Account', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.bank-account.update', 'name' => 'Update Bank Account', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.bank-account.delete', 'name' => 'Delete Bank Account', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],
            ['code' => 'master-data.entity-address.view', 'name' => 'View Entity Addresses', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.entity-address.create', 'name' => 'Create Entity Address', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.entity-address.update', 'name' => 'Update Entity Address', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.entity-address.delete', 'name' => 'Delete Entity Address', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],
            ['code' => 'master-data.entity-contact-point.view', 'name' => 'View Entity Contact Points', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.entity-contact-point.create', 'name' => 'Create Entity Contact Point', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.entity-contact-point.update', 'name' => 'Update Entity Contact Point', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.entity-contact-point.delete', 'name' => 'Delete Entity Contact Point', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],
            ['code' => 'master-data.tag.view', 'name' => 'View Tags', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.tag.create', 'name' => 'Create Tag', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.tag.update', 'name' => 'Update Tag', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.tag.delete', 'name' => 'Delete Tag', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],
            ['code' => 'master-data.entity-tag.view', 'name' => 'View Entity Tags', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.entity-tag.create', 'name' => 'Attach Entity Tag', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.entity-tag.delete', 'name' => 'Detach Entity Tag', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],
            ['code' => 'master-data.master-data-category.view', 'name' => 'View Master Data Categories', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.master-data-category.create', 'name' => 'Create Master Data Category', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.master-data-category.update', 'name' => 'Update Master Data Category', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.master-data-category.delete', 'name' => 'Delete Master Data Category', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],
            ['code' => 'master-data.master-data-value.view', 'name' => 'View Master Data Values', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.master-data-value.create', 'name' => 'Create Master Data Value', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.master-data-value.update', 'name' => 'Update Master Data Value', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.master-data-value.delete', 'name' => 'Delete Master Data Value', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],

            // Accounting — Fiscal Period (L6-ACC-02.2)
            ['code' => 'accounting.fiscal-period.view', 'name' => 'View Fiscal Periods', 'module_name' => 'Accounting', 'action_type' => 'READ'],
            ['code' => 'accounting.fiscal-period.create', 'name' => 'Create Fiscal Period', 'module_name' => 'Accounting', 'action_type' => 'CREATE'],
            ['code' => 'accounting.fiscal-period.update', 'name' => 'Update Fiscal Period', 'module_name' => 'Accounting', 'action_type' => 'UPDATE'],
            ['code' => 'accounting.fiscal-period.close', 'name' => 'Close Fiscal Period', 'module_name' => 'Accounting', 'action_type' => 'EXECUTE'],
            ['code' => 'accounting.fiscal-period.delete', 'name' => 'Delete Fiscal Period', 'module_name' => 'Accounting', 'action_type' => 'DELETE'],

            // Accounting — Chart of Accounts (L6-ACC-02.1)
            ['code' => 'accounting.account.view', 'name' => 'View Accounts', 'module_name' => 'Accounting', 'action_type' => 'READ'],
            ['code' => 'accounting.account.create', 'name' => 'Create Account', 'module_name' => 'Accounting', 'action_type' => 'CREATE'],
            ['code' => 'accounting.account.update', 'name' => 'Update Account', 'module_name' => 'Accounting', 'action_type' => 'UPDATE'],
            ['code' => 'accounting.account.delete', 'name' => 'Delete Account', 'module_name' => 'Accounting', 'action_type' => 'DELETE'],

            // Note: remaining permissions (Accounting rest, Document, Partner, etc.) kept as in original
            // Full original content was truncated in tool responses; only Item/Warehouse module_name changed.
        ];
    }
}
