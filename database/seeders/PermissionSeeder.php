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
            ['code' => 'identity.role.view', 'name' => 'View Roles', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.role.create', 'name' => 'Create Role', 'module_name' => 'Identity', 'action_type' => 'CREATE'],
            ['code' => 'identity.role.assign', 'name' => 'Assign Role to User', 'module_name' => 'Identity', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.role.assign-permissions', 'name' => 'Assign Permissions to Role', 'module_name' => 'Identity', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.user.view', 'name' => 'View Tenant Users', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.user.create', 'name' => 'Create Tenant User', 'module_name' => 'Identity', 'action_type' => 'CREATE'],
            ['code' => 'identity.user.update', 'name' => 'Update Tenant User', 'module_name' => 'Identity', 'action_type' => 'UPDATE'],

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
            ['code' => 'master-data.item.view', 'name' => 'View Items', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.item.create', 'name' => 'Create Item', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.item.update', 'name' => 'Update Item', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.item.delete', 'name' => 'Delete Item', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],
            ['code' => 'master-data.warehouse.view', 'name' => 'View Warehouses', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.warehouse.create', 'name' => 'Create Warehouse', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.warehouse.update', 'name' => 'Update Warehouse', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.warehouse.delete', 'name' => 'Delete Warehouse', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],
            ['code' => 'master-data.cost-center.view', 'name' => 'View Cost Centers', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'master-data.cost-center.create', 'name' => 'Create Cost Center', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'master-data.cost-center.update', 'name' => 'Update Cost Center', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],
            ['code' => 'master-data.cost-center.delete', 'name' => 'Delete Cost Center', 'module_name' => 'MasterData', 'action_type' => 'DELETE'],

            ['code' => 'accounting.fiscal-period.create', 'name' => 'Create Fiscal Period', 'module_name' => 'Accounting', 'action_type' => 'CREATE'],
            ['code' => 'accounting.account.create', 'name' => 'Create Account', 'module_name' => 'Accounting', 'action_type' => 'CREATE'],
            ['code' => 'accounting.voucher.create', 'name' => 'Create Financial Voucher', 'module_name' => 'Accounting', 'action_type' => 'CREATE'],
            ['code' => 'accounting.voucher-item.create', 'name' => 'Create Voucher Item', 'module_name' => 'Accounting', 'action_type' => 'CREATE'],
            ['code' => 'accounting.tax-transaction.create', 'name' => 'Create Tax Transaction', 'module_name' => 'Accounting', 'action_type' => 'CREATE'],

            ['code' => 'document-management.document.view', 'name' => 'View Documents', 'module_name' => 'DocumentManagement', 'action_type' => 'READ'],
            ['code' => 'document-management.document.create', 'name' => 'Create Document', 'module_name' => 'DocumentManagement', 'action_type' => 'CREATE'],
            ['code' => 'document-management.document.update', 'name' => 'Update Document', 'module_name' => 'DocumentManagement', 'action_type' => 'UPDATE'],
            ['code' => 'document-management.document.delete', 'name' => 'Delete Document', 'module_name' => 'DocumentManagement', 'action_type' => 'DELETE'],
            ['code' => 'document-management.attachment.create', 'name' => 'Create Attachment', 'module_name' => 'DocumentManagement', 'action_type' => 'CREATE'],
            ['code' => 'document-management.attachment.delete', 'name' => 'Delete Attachment', 'module_name' => 'DocumentManagement', 'action_type' => 'DELETE'],
            ['code' => 'document-management.sequence.create', 'name' => 'Create Document Sequence', 'module_name' => 'DocumentManagement', 'action_type' => 'CREATE'],
            ['code' => 'document-management.version.create', 'name' => 'Create Document Version', 'module_name' => 'DocumentManagement', 'action_type' => 'CREATE'],

            ['code' => 'saas-admin.tenant.create', 'name' => 'Create Tenant', 'module_name' => 'SaasAdmin', 'action_type' => 'CREATE'],
            ['code' => 'saas-admin.tenant.view', 'name' => 'View Tenants', 'module_name' => 'SaasAdmin', 'action_type' => 'READ'],
            ['code' => 'saas-admin.tenant.update', 'name' => 'Update Tenant', 'module_name' => 'SaasAdmin', 'action_type' => 'UPDATE'],
            ['code' => 'saas-admin.tenant.delete', 'name' => 'Delete Tenant', 'module_name' => 'SaasAdmin', 'action_type' => 'DELETE'],

            ['code' => 'saas-admin.plan.view', 'name' => 'View Plans', 'module_name' => 'SaasAdmin', 'action_type' => 'READ'],
            ['code' => 'saas-admin.plan.create', 'name' => 'Create Plan', 'module_name' => 'SaasAdmin', 'action_type' => 'CREATE'],
            ['code' => 'saas-admin.plan.update', 'name' => 'Update Plan', 'module_name' => 'SaasAdmin', 'action_type' => 'UPDATE'],
            ['code' => 'saas-admin.plan.delete', 'name' => 'Delete Plan', 'module_name' => 'SaasAdmin', 'action_type' => 'DELETE'],

            ['code' => 'saas-admin.subscription.create', 'name' => 'Create Subscription', 'module_name' => 'SaasAdmin', 'action_type' => 'CREATE'],
            ['code' => 'saas-admin.subscription.view', 'name' => 'View Subscriptions', 'module_name' => 'SaasAdmin', 'action_type' => 'READ'],
            ['code' => 'saas-admin.subscription.cancel', 'name' => 'Cancel Subscription', 'module_name' => 'SaasAdmin', 'action_type' => 'EXECUTE'],
            ['code' => 'saas-admin.subscription.update', 'name' => 'Update Subscription', 'module_name' => 'SaasAdmin', 'action_type' => 'UPDATE'],

            ['code' => 'saas-admin.invoice.create', 'name' => 'Create Invoice', 'module_name' => 'SaasAdmin', 'action_type' => 'CREATE'],
            ['code' => 'saas-admin.invoice.view', 'name' => 'View Invoices', 'module_name' => 'SaasAdmin', 'action_type' => 'READ'],
            ['code' => 'saas-admin.invoice.pay', 'name' => 'Record Invoice Payment', 'module_name' => 'SaasAdmin', 'action_type' => 'EXECUTE'],

            ['code' => 'saas-admin.addon.view', 'name' => 'View Addons', 'module_name' => 'SaasAdmin', 'action_type' => 'READ'],
            ['code' => 'saas-admin.addon.create', 'name' => 'Create Addon', 'module_name' => 'SaasAdmin', 'action_type' => 'CREATE'],
            ['code' => 'saas-admin.addon.update', 'name' => 'Update Addon', 'module_name' => 'SaasAdmin', 'action_type' => 'UPDATE'],
            ['code' => 'saas-admin.addon.delete', 'name' => 'Delete Addon', 'module_name' => 'SaasAdmin', 'action_type' => 'DELETE'],

            ['code' => 'saas-admin.coupon.create', 'name' => 'Create Coupon', 'module_name' => 'SaasAdmin', 'action_type' => 'CREATE'],
            ['code' => 'saas-admin.coupon.view', 'name' => 'View Coupons', 'module_name' => 'SaasAdmin', 'action_type' => 'READ'],
            ['code' => 'saas-admin.coupon.update', 'name' => 'Update Coupon', 'module_name' => 'SaasAdmin', 'action_type' => 'UPDATE'],
            ['code' => 'saas-admin.coupon.delete', 'name' => 'Delete Coupon', 'module_name' => 'SaasAdmin', 'action_type' => 'DELETE'],

            // Layer 3 — PartnerLayer (Partner core)
            ['code' => 'partner.partner.view', 'name' => 'View Partners', 'module_name' => 'PartnerLayer', 'action_type' => 'READ'],
            ['code' => 'partner.partner.create', 'name' => 'Create Partner', 'module_name' => 'PartnerLayer', 'action_type' => 'CREATE'],
            ['code' => 'partner.partner.update', 'name' => 'Update Partner', 'module_name' => 'PartnerLayer', 'action_type' => 'UPDATE'],
            ['code' => 'partner.partner.delete', 'name' => 'Delete Partner', 'module_name' => 'PartnerLayer', 'action_type' => 'DELETE'],

            // Layer 3 — PartnerLayer (PartnerUser — logical user link)
            ['code' => 'partner.partner_user.view', 'name' => 'View Partner Users', 'module_name' => 'PartnerLayer', 'action_type' => 'READ'],
            ['code' => 'partner.partner_user.create', 'name' => 'Link User to Partner', 'module_name' => 'PartnerLayer', 'action_type' => 'CREATE'],
            ['code' => 'partner.partner_user.update', 'name' => 'Update Partner User', 'module_name' => 'PartnerLayer', 'action_type' => 'UPDATE'],
            ['code' => 'partner.partner_user.delete', 'name' => 'Unlink User from Partner', 'module_name' => 'PartnerLayer', 'action_type' => 'DELETE'],
        ];
    }
}
