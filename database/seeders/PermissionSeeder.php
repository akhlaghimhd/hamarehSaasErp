<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    use PermissionCatalog;

    public function run(): void
    {
        $tenantIds = $this->resolveTenantIds();
        if ($tenantIds === []) {
            $this->command?->error('No tenants found. Seed tenants first.');
            return;
        }

        $permissions = $this->getBasePermissions();

        foreach ($tenantIds as $tenantId) {
            DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);
            $permissionIds = $this->upsertPermissionsForTenant($tenantId, $permissions);
            $roleId = $this->ensureTenantAdminRole($tenantId, $permissionIds);
            $this->assignTenantAdminToDemoMembers($tenantId, $roleId);
            $this->seedDemoRoles($tenantId);
            $this->command?->info("Seeded permissions/roles for tenant {$tenantId} (".count($permissionIds)." codes)");
        }
    }

    /** @return list<string> */
    private function resolveTenantIds(): array
    {
        $ids = [];

        $fromEnv = env('DEMO_TENANT_ID');
        if (is_string($fromEnv) && $fromEnv !== '') {
            $ids[] = $fromEnv;
        }

        $ids[] = '3ab77cac-1343-4b13-8e14-0d887aad132a';

        if (Schema::hasTable('tenants')) {
            foreach (DB::table('tenants')->pluck('tenant_id')->all() as $id) {
                $ids[] = (string) $id;
            }
        }

        if (Schema::hasTable('tenant_users')) {
            foreach (DB::table('tenant_users')->distinct()->pluck('tenant_id')->all() as $id) {
                $ids[] = (string) $id;
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));

        if (Schema::hasTable('tenants')) {
            $existing = DB::table('tenants')->whereIn('tenant_id', $ids)->pluck('tenant_id')->all();
            if ($existing !== []) {
                return array_map('strval', $existing);
            }
        }

        return $ids;
    }

    /** @param list<array<string, mixed>> $permissions @return list<string> */
    private function upsertPermissionsForTenant(string $tenantId, array $permissions): array
    {
        $permissionIds = [];

        foreach ($permissions as $perm) {
            $existing = DB::table('tenant_permissions')
                ->where('tenant_id', $tenantId)
                ->where('code', $perm['code'])
                ->first();

            if ($existing) {
                DB::table('tenant_permissions')
                    ->where('tenant_permission_id', $existing->tenant_permission_id)
                    ->update([
                        'name' => $perm['name'],
                        'module_name' => $perm['module_name'],
                        'action_type' => $perm['action_type'] ?? null,
                        'description' => $perm['description'] ?? null,
                        'status' => 1,
                        'updated_at' => now(),
                    ]);
                $permissionIds[] = $existing->tenant_permission_id;
            } else {
                $permissionId = (string) Str::uuid();
                DB::table('tenant_permissions')->insert([
                    'tenant_permission_id' => $permissionId,
                    'tenant_id' => $tenantId,
                    'code' => $perm['code'],
                    'name' => $perm['name'],
                    'module_name' => $perm['module_name'],
                    'action_type' => $perm['action_type'] ?? null,
                    'description' => $perm['description'] ?? null,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $permissionIds[] = $permissionId;
            }
        }

        return $permissionIds;
    }

    /** @param list<string> $permissionIds */
    private function ensureTenantAdminRole(string $tenantId, array $permissionIds): string
    {
        $existingRole = DB::table('tenant_roles')
            ->where('tenant_id', $tenantId)
            ->where('code', 'tenant-admin')
            ->first();

        if ($existingRole) {
            $actualRoleId = $existingRole->tenant_role_id;
            DB::table('tenant_roles')->where('tenant_role_id', $actualRoleId)->update([
                'name' => 'مدیر سازمان',
                'description' => 'دسترسی کامل به تمام قابلیت‌های سازمان',
                'status' => 1,
                'updated_at' => now(),
            ]);
        } else {
            $actualRoleId = (string) Str::uuid();
            DB::table('tenant_roles')->insert([
                'tenant_role_id' => $actualRoleId,
                'tenant_id' => $tenantId,
                'code' => 'tenant-admin',
                'name' => 'مدیر سازمان',
                'description' => 'دسترسی کامل به تمام قابلیت‌های سازمان',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('tenant_role_permissions')
            ->where('tenant_id', $tenantId)
            ->where('tenant_role_id', $actualRoleId)
            ->delete();

        $insertData = [];
        foreach ($permissionIds as $permId) {
            $insertData[] = [
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'tenant_role_id' => $actualRoleId,
                'tenant_permission_id' => $permId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if (!empty($insertData)) {
            DB::table('tenant_role_permissions')->insert($insertData);
        }

        return $actualRoleId;
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
        $targets = $owners->isNotEmpty() ? $owners : $query->get(['user_id']);

        foreach ($targets as $row) {
            $exists = DB::table('tenant_user_roles')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $row->user_id)
                ->where('tenant_role_id', $roleId)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('tenant_user_roles')->insert([
                'tenant_user_role_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id' => $row->user_id,
                'tenant_role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedDemoRoles(string $tenantId): void
    {
        $demoRoles = [
            [
                'code' => 'accountant',
                'name' => 'حسابدار',
                'description' => 'دسترسی به اسناد و حساب‌ها',
                'permission_codes' => ['accounting.voucher.view', 'accounting.voucher.post', 'accounting.account.view', 'identity.user.view', 'identity.profile.view'],
            ],
            [
                'code' => 'sales',
                'name' => 'فروش',
                'description' => 'سفارش و فاکتور فروش',
                'permission_codes' => ['procurement.sales-order.create', 'procurement.sales-order.view', 'procurement.sales-order.confirm', 'procurement.sales-invoice.view', 'procurement.sales-invoice.create', 'identity.user.view', 'identity.profile.view'],
            ],
            [
                'code' => 'warehouse',
                'name' => 'انباردار',
                'description' => 'کالا، انبار و اسناد موجودی',
                'permission_codes' => ['inventory.item.view', 'inventory.item.create', 'inventory.warehouse.view', 'inventory.document.view', 'inventory.document.create', 'inventory.document.post', 'identity.user.view', 'identity.profile.view'],
            ],
            [
                'code' => 'hr-viewer',
                'name' => 'مشاهده‌گر منابع انسانی',
                'description' => 'فقط مشاهده کاربران و تاریخچه',
                'permission_codes' => ['identity.user.view', 'identity.profile.view', 'identity.membership_history.view', 'identity.role.view'],
            ],
            [
                'code' => 'purchase',
                'name' => 'خرید',
                'description' => 'سفارش خرید و رسید',
                'permission_codes' => ['procurement.purchase-order.create', 'procurement.purchase-receipt.create', 'procurement.purchase-receipt.view', 'procurement.purchase-receipt.post', 'procurement.purchase-requisition.view', 'procurement.purchase-requisition.create', 'identity.user.view', 'identity.profile.view'],
            ],
        ];

        $codeToId = [];
        foreach (DB::table('tenant_permissions')->where('tenant_id', $tenantId)->get(['tenant_permission_id', 'code']) as $row) {
            $codeToId[$row->code] = $row->tenant_permission_id;
        }

        foreach ($demoRoles as $roleDef) {
            $existing = DB::table('tenant_roles')->where('tenant_id', $tenantId)->where('code', $roleDef['code'])->first();
            if ($existing) {
                $roleId = $existing->tenant_role_id;
                DB::table('tenant_roles')->where('tenant_role_id', $roleId)->update([
                    'name' => $roleDef['name'],
                    'description' => $roleDef['description'],
                    'status' => 1,
                    'updated_at' => now(),
                ]);
            } else {
                $roleId = (string) Str::uuid();
                DB::table('tenant_roles')->insert([
                    'tenant_role_id' => $roleId,
                    'tenant_id' => $tenantId,
                    'code' => $roleDef['code'],
                    'name' => $roleDef['name'],
                    'description' => $roleDef['description'],
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('tenant_role_permissions')->where('tenant_id', $tenantId)->where('tenant_role_id', $roleId)->delete();

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
            if (!empty($insertData)) {
                DB::table('tenant_role_permissions')->insert($insertData);
            }
        }
    }
}
