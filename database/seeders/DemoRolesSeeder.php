<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Hierarchical demo roles for multi-role UI testing.
 * Run after PermissionSeeder (needs permissions + tenant).
 */
class DemoRolesSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = '3ab77cac-1343-4b13-8e14-0d887aad132a';
        $hasParent = Schema::hasColumn('tenant_roles', 'parent_role_id');

        $groups = [
            ['code' => 'group-finance', 'name' => 'مالی', 'description' => 'گروه نقش‌های مالی'],
            ['code' => 'group-sales', 'name' => 'فروش و بازاریابی', 'description' => 'گروه نقش‌های فروش'],
            ['code' => 'group-ops', 'name' => 'عملیات و انبار', 'description' => 'گروه نقش‌های عملیاتی'],
            ['code' => 'group-hr', 'name' => 'منابع انسانی', 'description' => 'گروه نقش‌های منابع انسانی'],
        ];

        $groupIds = [];
        foreach ($groups as $g) {
            $existing = DB::table('tenant_roles')
                ->where('tenant_id', $tenantId)
                ->where('code', $g['code'])
                ->first();

            if ($existing) {
                $groupIds[$g['code']] = $existing->tenant_role_id;
                DB::table('tenant_roles')->where('tenant_role_id', $existing->tenant_role_id)->update([
                    'name' => $g['name'],
                    'description' => $g['description'],
                    'status' => 1,
                    'updated_at' => now(),
                ]);
            } else {
                $id = (string) Str::uuid();
                $row = [
                    'tenant_role_id' => $id,
                    'tenant_id' => $tenantId,
                    'code' => $g['code'],
                    'name' => $g['name'],
                    'description' => $g['description'],
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if ($hasParent) {
                    $row['parent_role_id'] = null;
                }
                DB::table('tenant_roles')->insert($row);
                $groupIds[$g['code']] = $id;
            }
        }

        $codeToId = [];
        foreach (DB::table('tenant_permissions')->where('tenant_id', $tenantId)->get(['tenant_permission_id', 'code']) as $row) {
            $codeToId[$row->code] = $row->tenant_permission_id;
        }

        $demoRoles = [
            [
                'code' => 'accountant',
                'name' => 'حسابدار',
                'description' => 'دسترسی به اسناد و حساب‌ها',
                'parent_code' => 'group-finance',
                'permission_codes' => ['accounting.voucher.view', 'accounting.voucher.post', 'accounting.account.view', 'identity.user.view', 'identity.profile.view'],
            ],
            [
                'code' => 'sales',
                'name' => 'کارشناس فروش',
                'description' => 'سفارش و فاکتور فروش',
                'parent_code' => 'group-sales',
                'permission_codes' => ['procurement.sales-order.create', 'procurement.sales-order.view', 'procurement.sales-order.confirm', 'procurement.sales-invoice.view', 'procurement.sales-invoice.create', 'identity.user.view', 'identity.profile.view'],
            ],
            [
                'code' => 'sales-lead',
                'name' => 'سرپرست فروش',
                'description' => 'نظارت بر فروش',
                'parent_code' => 'group-sales',
                'permission_codes' => ['procurement.sales-order.create', 'procurement.sales-order.view', 'procurement.sales-order.confirm', 'procurement.sales-invoice.view', 'procurement.sales-invoice.create', 'procurement.sales-invoice.post', 'identity.user.view', 'identity.profile.view'],
            ],
            [
                'code' => 'warehouse',
                'name' => 'انباردار',
                'description' => 'کالا، انبار و اسناد موجودی',
                'parent_code' => 'group-ops',
                'permission_codes' => ['inventory.item.view', 'inventory.item.create', 'inventory.warehouse.view', 'inventory.document.view', 'inventory.document.create', 'inventory.document.post', 'identity.user.view', 'identity.profile.view'],
            ],
            [
                'code' => 'purchase',
                'name' => 'کارشناس خرید',
                'description' => 'سفارش خرید و رسید',
                'parent_code' => 'group-ops',
                'permission_codes' => ['procurement.purchase-order.create', 'procurement.purchase-receipt.create', 'procurement.purchase-receipt.view', 'procurement.purchase-receipt.post', 'procurement.purchase-requisition.view', 'procurement.purchase-requisition.create', 'identity.user.view', 'identity.profile.view'],
            ],
            [
                'code' => 'hr-viewer',
                'name' => 'مشاهده‌گر منابع انسانی',
                'description' => 'فقط مشاهده کاربران و تاریخچه',
                'parent_code' => 'group-hr',
                'permission_codes' => ['identity.user.view', 'identity.profile.view', 'identity.membership_history.view', 'identity.role.view'],
            ],
        ];

        foreach ($demoRoles as $roleDef) {
            $parentId = $groupIds[$roleDef['parent_code']] ?? null;
            $existing = DB::table('tenant_roles')
                ->where('tenant_id', $tenantId)
                ->where('code', $roleDef['code'])
                ->first();

            if ($existing) {
                $roleId = $existing->tenant_role_id;
                $update = [
                    'name' => $roleDef['name'],
                    'description' => $roleDef['description'],
                    'status' => 1,
                    'updated_at' => now(),
                ];
                if ($hasParent) {
                    $update['parent_role_id'] = $parentId;
                }
                DB::table('tenant_roles')->where('tenant_role_id', $roleId)->update($update);
            } else {
                $roleId = (string) Str::uuid();
                $insert = [
                    'tenant_role_id' => $roleId,
                    'tenant_id' => $tenantId,
                    'code' => $roleDef['code'],
                    'name' => $roleDef['name'],
                    'description' => $roleDef['description'],
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if ($hasParent) {
                    $insert['parent_role_id'] = $parentId;
                }
                DB::table('tenant_roles')->insert($insert);
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
                    'tenant_id' => $tenantId,
                    'tenant_role_id' => $roleId,
                    'tenant_permission_id' => $codeToId[$code],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($insertData !== []) {
                DB::table('tenant_role_permissions')->insert($insertData);
            }
        }
    }
}
