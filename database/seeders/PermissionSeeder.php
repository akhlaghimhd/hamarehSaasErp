<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        $this->assignTenantAdminToDemoMembers($demoTenantId, $actualRoleId);

        $this->seedDemoRoles($demoTenantId);
    }

    private function assignTenantAdminToDemoMembers(string $tenantId, string $roleId): void
    {
        if (!Schema::hasTable('tenant_users') || !Schema::hasTable('tenant_user_roles')) {
            return;
        }

        $query = DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->whereNull('deleted_at');

        $owners = (clone $query)->where('is_owner', true)->get(['user_id']);
        $targets = $owners->isNotEmpty()
            ? $owners
            : $query->get(['user_id']);

        foreach ($targets as $row) {
            $exists = DB::table('tenant_user_roles')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $row->user_id)
                ->where('tenant_role_id', $roleId)
                ->exists();

            if ($exists) {
                continue;
            }

            $payload = [
                'tenant_user_role_id' => (string) Str::uuid(),
                'tenant_id'           => $tenantId,
                'user_id'             => $row->user_id,
                'tenant_role_id'      => $roleId,
                'created_at'          => now(),
                'updated_at'          => now(),
            ];

            DB::table('tenant_user_roles')->insert($payload);
        }
    }

    /**
     * Extra demo roles so multi-role assignment can be tested in the UI.
     * Idempotent by role code per tenant.
     */
    private function seedDemoRoles(string $tenantId): void
    {
        $demoRoles = [
            [
                'code' => 'accountant',
                'name' => 'حسابدار',
                'description' => 'دسترسی به اسناد و حساب‌ها',
                'permission_codes' => [
                    'accounting.voucher.view',
                    'accounting.voucher.post',
                    'accounting.account.view',
                    'identity.user.view',
                    'identity.profile.view',
                ],
            ],
            [
                'code' => 'sales',
                'name' => 'فروش',
                'description' => 'سفارش و فاکتور فروش',
                'permission_codes' => [
                    'procurement.sales-order.create',
                    'procurement.sales-order.view',
                    'procurement.sales-order.confirm',
                    'procurement.sales-invoice.view',
                    'procurement.sales-invoice.create',
                    'identity.user.view',
                    'identity.profile.view',
                ],
            ],
            [
                'code' => 'warehouse',
                'name' => 'انباردار',
                'description' => 'کالا، انبار و اسناد موجودی',
                'permission_codes' => [
                    'inventory.item.view',
                    'inventory.item.create',
                    'inventory.warehouse.view',
                    'inventory.document.view',
                    'inventory.document.create',
                    'inventory.document.post',
                    'identity.user.view',
                    'identity.profile.view',
                ],
            ],
            [
                'code' => 'hr-viewer',
                'name' => 'مشاهده‌گر منابع انسانی',
                'description' => 'فقط مشاهده کاربران و تاریخچه',
                'permission_codes' => [
                    'identity.user.view',
                    'identity.profile.view',
                    'identity.membership_history.view',
                    'identity.role.view',
                ],
            ],
            [
                'code' => 'purchase',
                'name' => 'خرید',
                'description' => 'سفارش خرید و رسید',
                'permission_codes' => [
                    'procurement.purchase-order.create',
                    'procurement.purchase-receipt.create',
                    'procurement.purchase-receipt.view',
                    'procurement.purchase-receipt.post',
                    'procurement.purchase-requisition.view',
                    'procurement.purchase-requisition.create',
                    'identity.user.view',
                    'identity.profile.view',
                ],
            ],
        ];

        $codeToId = [];
        $rows = DB::table('tenant_permissions')
            ->where('tenant_id', $tenantId)
            ->get(['tenant_permission_id', 'code']);
        foreach ($rows as $row) {
            $codeToId[$row->code] = $row->tenant_permission_id;
        }

        foreach ($demoRoles as $roleDef) {
            $existing = DB::table('tenant_roles')
                ->where('tenant_id', $tenantId)
                ->where('code', $roleDef['code'])
                ->first();

            if ($existing) {
                $roleId = $existing->tenant_role_id;
                DB::table('tenant_roles')
                    ->where('tenant_role_id', $roleId)
                    ->update([
                        'name'        => $roleDef['name'],
                        'description' => $roleDef['description'],
                        'status'      => 1,
                        'updated_at'  => now(),
                    ]);
            } else {
                $roleId = (string) Str::uuid();
                DB::table('tenant_roles')->insert([
                    'tenant_role_id' => $roleId,
                    'tenant_id'      => $tenantId,
                    'code'           => $roleDef['code'],
                    'name'           => $roleDef['name'],
                    'description'    => $roleDef['description'],
                    'status'         => 1,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            DB::table('tenant_role_permissions')
                ->where('tenant_id', $tenantId)
                ->where('tenant_role_id', $roleId)
                ->delete();

            $insertData = [];
            foreach ($roleDef['permission_codes'] as $code) {
                if (!isset($codeToId[$code])) {
                    continue;
                }
                $insertData[] = [
                    'tenant_role_permission_id' => (string) Str::uuid(),
                    'tenant_id'                 => $tenantId,
                    'tenant_role_id'            => $roleId,
                    'tenant_permission_id'      => $codeToId[$code],
                    'created_at'                => now(),
                    'updated_at'                => now(),
                ];
            }
            if (!empty($insertData)) {
                DB::table('tenant_role_permissions')->insert($insertData);
            }
        }
    }

    private function getBasePermissions(): array
    {
        return [
            ['code' => 'identity.user.view', 'name' => 'مشاهده کاربران', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.user.create', 'name' => 'ایجاد کاربر', 'module_name' => 'Identity', 'action_type' => 'CREATE'],
            ['code' => 'identity.user.update', 'name' => 'ویرایش کاربر', 'module_name' => 'Identity', 'action_type' => 'UPDATE'],
            ['code' => 'identity.user.delete', 'name' => 'حذف کاربر', 'module_name' => 'Identity', 'action_type' => 'DELETE'],
            ['code' => 'identity.user.restore', 'name' => 'بازگردانی کاربر حذف‌شده', 'module_name' => 'Identity', 'action_type' => 'EXECUTE'],

            ['code' => 'identity.role.view', 'name' => 'مشاهده نقش‌ها', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.role.create', 'name' => 'ایجاد نقش', 'module_name' => 'Identity', 'action_type' => 'CREATE'],
            ['code' => 'identity.role.update', 'name' => 'ویرایش نقش', 'module_name' => 'Identity', 'action_type' => 'UPDATE'],
            ['code' => 'identity.role.delete', 'name' => 'حذف نقش', 'module_name' => 'Identity', 'action_type' => 'DELETE'],
            ['code' => 'identity.role.assign', 'name' => 'تخصیص نقش به کاربر', 'module_name' => 'Identity', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.role.assign-permissions', 'name' => 'تخصیص مجوز به نقش', 'module_name' => 'Identity', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.role.manage', 'name' => 'مدیریت نقش‌ها (قدیمی)', 'module_name' => 'Identity', 'action_type' => 'EXECUTE'],

            ['code' => 'identity.permission.view', 'name' => 'مشاهده مجوزها', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.permission.create', 'name' => 'ایجاد مجوز', 'module_name' => 'Identity', 'action_type' => 'CREATE'],
            ['code' => 'identity.permission.update', 'name' => 'ویرایش مجوز', 'module_name' => 'Identity', 'action_type' => 'UPDATE'],
            ['code' => 'identity.permission.delete', 'name' => 'حذف مجوز', 'module_name' => 'Identity', 'action_type' => 'DELETE'],

            ['code' => 'identity.scope.view', 'name' => 'مشاهده محدوده دسترسی', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.scope.create', 'name' => 'ایجاد محدوده دسترسی', 'module_name' => 'Identity', 'action_type' => 'CREATE'],
            ['code' => 'identity.scope.update', 'name' => 'ویرایش محدوده دسترسی', 'module_name' => 'Identity', 'action_type' => 'UPDATE'],
            ['code' => 'identity.scope.delete', 'name' => 'حذف محدوده دسترسی', 'module_name' => 'Identity', 'action_type' => 'DELETE'],
            ['code' => 'identity.scope.assign', 'name' => 'تخصیص محدوده دسترسی', 'module_name' => 'Identity', 'action_type' => 'EXECUTE'],

            ['code' => 'identity.profile.view', 'name' => 'مشاهده پروفایل', 'module_name' => 'Identity', 'action_type' => 'READ'],
            ['code' => 'identity.profile.update', 'name' => 'ویرایش پروفایل', 'module_name' => 'Identity', 'action_type' => 'UPDATE'],
            ['code' => 'identity.profile.delete', 'name' => 'حذف پروفایل', 'module_name' => 'Identity', 'action_type' => 'DELETE'],

            ['code' => 'identity.membership_history.view', 'name' => 'مشاهده تاریخچه عضویت', 'module_name' => 'Identity', 'action_type' => 'READ'],

            ['code' => 'masterdata.business-partner.view', 'name' => 'View Business Partners', 'module_name' => 'MasterData', 'action_type' => 'READ'],
            ['code' => 'masterdata.business-partner.create', 'name' => 'Create Business Partner', 'module_name' => 'MasterData', 'action_type' => 'CREATE'],
            ['code' => 'masterdata.business-partner.update', 'name' => 'Update Business Partner', 'module_name' => 'MasterData', 'action_type' => 'UPDATE'],

            ['code' => 'inventory.item.view', 'name' => 'View Items', 'module_name' => 'Inventory', 'action_type' => 'READ'],
            ['code' => 'inventory.item.create', 'name' => 'Create Item', 'module_name' => 'Inventory', 'action_type' => 'CREATE'],
            ['code' => 'inventory.warehouse.view', 'name' => 'View Warehouses', 'module_name' => 'Inventory', 'action_type' => 'READ'],
            ['code' => 'inventory.document.view', 'name' => 'View Inventory Documents', 'module_name' => 'Inventory', 'action_type' => 'READ'],
            ['code' => 'inventory.document.create', 'name' => 'Create Inventory Document', 'module_name' => 'Inventory', 'action_type' => 'CREATE'],
            ['code' => 'inventory.document.post', 'name' => 'Post Inventory Document', 'module_name' => 'Inventory', 'action_type' => 'EXECUTE'],

            ['code' => 'accounting.voucher.view', 'name' => 'View Vouchers', 'module_name' => 'Accounting', 'action_type' => 'READ'],
            ['code' => 'accounting.voucher.post', 'name' => 'Post Voucher', 'module_name' => 'Accounting', 'action_type' => 'EXECUTE'],
            ['code' => 'accounting.account.view', 'name' => 'View Accounts', 'module_name' => 'Accounting', 'action_type' => 'READ'],

            ['code' => 'procurement.purchase-order.create', 'name' => 'Create Purchase Order', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.purchase-receipt.create', 'name' => 'Create Purchase Receipt', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.purchase-receipt.view', 'name' => 'View Purchase Receipt', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.purchase-receipt.post', 'name' => 'Post Purchase Receipt', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],
            ['code' => 'procurement.sales-order.create', 'name' => 'Create Sales Order', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.sales-order.view', 'name' => 'View Sales Order', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.sales-order.confirm', 'name' => 'Confirm Sales Order', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],
            ['code' => 'procurement.sales-delivery.create', 'name' => 'Create Sales Delivery', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.sales-delivery.view', 'name' => 'View Sales Delivery', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.sales-delivery.post', 'name' => 'Post Sales Delivery', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],
            ['code' => 'procurement.sales-quotation.create', 'name' => 'Create Sales Quotation', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.return-order.create', 'name' => 'Create Return Order', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],

            ['code' => 'procurement.sales-invoice.view', 'name' => 'View Sales Invoices', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.sales-invoice.create', 'name' => 'Create Sales Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.sales-invoice.update', 'name' => 'Update Sales Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'UPDATE'],
            ['code' => 'procurement.sales-invoice.post', 'name' => 'Post Sales Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],
            ['code' => 'procurement.purchase-invoice.view', 'name' => 'View Purchase Invoices', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.purchase-invoice.create', 'name' => 'Create Purchase Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.purchase-invoice.post', 'name' => 'Post Purchase Invoice', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],

            ['code' => 'procurement.payment-schedule.view', 'name' => 'View Payment Schedules', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.cash-transaction.create', 'name' => 'Create Cash Transactions', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.cash-transaction.view', 'name' => 'View Cash Transactions', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],

            ['code' => 'procurement.purchase-requisition.view', 'name' => 'View Purchase Requisitions', 'module_name' => 'ProcurementSales', 'action_type' => 'READ'],
            ['code' => 'procurement.purchase-requisition.create', 'name' => 'Create Purchase Requisition', 'module_name' => 'ProcurementSales', 'action_type' => 'CREATE'],
            ['code' => 'procurement.purchase-requisition.submit', 'name' => 'Submit Purchase Requisition', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],
            ['code' => 'procurement.purchase-requisition.approve', 'name' => 'Approve Purchase Requisition', 'module_name' => 'ProcurementSales', 'action_type' => 'EXECUTE'],

            ['code' => 'workflow.definition.manage', 'name' => 'Manage Workflow Definitions', 'module_name' => 'Workflow', 'action_type' => 'EXECUTE'],
            ['code' => 'workflow.instance.start', 'name' => 'Start Workflow Instance', 'module_name' => 'Workflow', 'action_type' => 'EXECUTE'],
            ['code' => 'workflow.instance.view', 'name' => 'View Workflow Instance', 'module_name' => 'Workflow', 'action_type' => 'READ'],
            ['code' => 'workflow.task.view', 'name' => 'View Workflow Worklist', 'module_name' => 'Workflow', 'action_type' => 'READ'],
            ['code' => 'workflow.task.complete', 'name' => 'Complete Workflow Task', 'module_name' => 'Workflow', 'action_type' => 'EXECUTE'],
        ];
    }
}
