<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ensures every tenant has a full permission catalog + tenant-admin + demo roles.
 *
 * Strategy:
 * 1) Prefer cloning from the tenant that already has the most permission rows.
 * 2) If none exist, fall back to minimal identity set + organization catalog.
 * 3) Always ensure organization.* catalog rows exist (Foundation + P0–P6 resources).
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('tenant_permissions') || !Schema::hasTable('tenants')) {
            $this->command?->error('Required tables missing.');
            return;
        }

        $tenantIds = DB::table('tenants')->pluck('tenant_id')->map(fn ($id) => (string) $id)->all();
        if ($tenantIds === []) {
            $this->command?->error('No tenants found.');
            return;
        }

        $sourceTenantId = DB::table('tenant_permissions')
            ->select('tenant_id', DB::raw('COUNT(*) as c'))
            ->groupBy('tenant_id')
            ->orderByDesc('c')
            ->value('tenant_id');

        if (!$sourceTenantId) {
            $sourceTenantId = $tenantIds[0];
            $this->bootstrapMinimalCatalog((string) $sourceTenantId);
        } else {
            $sourceTenantId = (string) $sourceTenantId;
        }

        $this->ensureOrganizationCatalog($sourceTenantId);

        $sourcePerms = DB::table('tenant_permissions')
            ->where('tenant_id', $sourceTenantId)
            ->whereNull('deleted_at')
            ->get();

        if ($sourcePerms->isEmpty()) {
            $this->bootstrapMinimalCatalog($sourceTenantId);
            $this->ensureOrganizationCatalog($sourceTenantId);
            $sourcePerms = DB::table('tenant_permissions')
                ->where('tenant_id', $sourceTenantId)
                ->whereNull('deleted_at')
                ->get();
        }

        foreach ($tenantIds as $tenantId) {
            DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);
            $this->ensureOrganizationCatalog($tenantId);
            $map = $this->syncPermissions($tenantId, $sourcePerms);
            $map = array_merge($map, $this->mapCodes($tenantId, array_column($this->organizationCatalog(), 'code')));
            $adminRoleId = $this->ensureAdminRole($tenantId, array_values($map));
            $this->assignAdminToOwners($tenantId, $adminRoleId);
            $this->seedDemoRoles($tenantId, $map);
            $this->command?->info("Tenant {$tenantId}: ".count($map).' permissions, admin role ready');
        }
    }

    /** @return list<array{code:string,name:string,module_name:string,action_type:string}> */
    private function organizationCatalog(): array
    {
        return [
            // Core company / branch / department
            ['code' => 'organization.company.view', 'name' => 'مشاهده شرکت‌ها', 'module_name' => 'سازمان', 'action_type' => 'READ'],
            ['code' => 'organization.company.create', 'name' => 'ایجاد شرکت', 'module_name' => 'سازمان', 'action_type' => 'CREATE'],
            ['code' => 'organization.company.update', 'name' => 'ویرایش شرکت', 'module_name' => 'سازمان', 'action_type' => 'UPDATE'],
            ['code' => 'organization.company.delete', 'name' => 'حذف شرکت', 'module_name' => 'سازمان', 'action_type' => 'DELETE'],
            ['code' => 'organization.branch.view', 'name' => 'مشاهده شعب', 'module_name' => 'سازمان', 'action_type' => 'READ'],
            ['code' => 'organization.branch.create', 'name' => 'ایجاد شعبه', 'module_name' => 'سازمان', 'action_type' => 'CREATE'],
            ['code' => 'organization.branch.update', 'name' => 'ویرایش شعبه', 'module_name' => 'سازمان', 'action_type' => 'UPDATE'],
            ['code' => 'organization.branch.delete', 'name' => 'حذف شعبه', 'module_name' => 'سازمان', 'action_type' => 'DELETE'],
            ['code' => 'organization.department.view', 'name' => 'مشاهده واحدها', 'module_name' => 'سازمان', 'action_type' => 'READ'],
            ['code' => 'organization.department.create', 'name' => 'ایجاد واحد سازمانی', 'module_name' => 'سازمان', 'action_type' => 'CREATE'],
            ['code' => 'organization.department.update', 'name' => 'ویرایش واحد سازمانی', 'module_name' => 'سازمان', 'action_type' => 'UPDATE'],
            ['code' => 'organization.department.delete', 'name' => 'حذف واحد سازمانی', 'module_name' => 'سازمان', 'action_type' => 'DELETE'],

            // P1 ownership / group
            ['code' => 'organization.ownership.view', 'name' => 'مشاهده مالکیت شرکت', 'module_name' => 'سازمان', 'action_type' => 'READ'],
            ['code' => 'organization.ownership.manage', 'name' => 'مدیریت مالکیت شرکت', 'module_name' => 'سازمان', 'action_type' => 'EXECUTE'],

            // P2 financial attrs / fiscal
            ['code' => 'organization.fiscal.view', 'name' => 'مشاهده انتساب دوره مالی', 'module_name' => 'سازمان', 'action_type' => 'READ'],
            ['code' => 'organization.fiscal.manage', 'name' => 'مدیریت انتساب دوره مالی', 'module_name' => 'سازمان', 'action_type' => 'EXECUTE'],

            // P3 bank / officers
            ['code' => 'organization.bank.view', 'name' => 'مشاهده حساب‌های بانکی شرکت', 'module_name' => 'سازمان', 'action_type' => 'READ'],
            ['code' => 'organization.bank.manage', 'name' => 'مدیریت حساب‌های بانکی شرکت', 'module_name' => 'سازمان', 'action_type' => 'EXECUTE'],
            ['code' => 'organization.officer.view', 'name' => 'مشاهده مقامات شرکت', 'module_name' => 'سازمان', 'action_type' => 'READ'],
            ['code' => 'organization.officer.manage', 'name' => 'مدیریت مقامات شرکت', 'module_name' => 'سازمان', 'action_type' => 'EXECUTE'],

            // P4 BU / cost center / hierarchy
            ['code' => 'organization.business_unit.view', 'name' => 'مشاهده واحد کسب‌وکار', 'module_name' => 'سازمان', 'action_type' => 'READ'],
            ['code' => 'organization.business_unit.manage', 'name' => 'مدیریت واحد کسب‌وکار', 'module_name' => 'سازمان', 'action_type' => 'EXECUTE'],
            ['code' => 'organization.cost_center.view', 'name' => 'مشاهده مراکز هزینه', 'module_name' => 'سازمان', 'action_type' => 'READ'],
            ['code' => 'organization.cost_center.manage', 'name' => 'مدیریت مراکز هزینه', 'module_name' => 'سازمان', 'action_type' => 'EXECUTE'],
            ['code' => 'organization.hierarchy.view', 'name' => 'مشاهده سلسله‌مراتب سازمانی', 'module_name' => 'سازمان', 'action_type' => 'READ'],
            ['code' => 'organization.hierarchy.manage', 'name' => 'مدیریت سلسله‌مراتب سازمانی', 'module_name' => 'سازمان', 'action_type' => 'EXECUTE'],

            // P5 sales/purch / IC
            ['code' => 'organization.sales_org.view', 'name' => 'مشاهده سازمان فروش', 'module_name' => 'سازمان', 'action_type' => 'READ'],
            ['code' => 'organization.sales_org.manage', 'name' => 'مدیریت سازمان فروش', 'module_name' => 'سازمان', 'action_type' => 'EXECUTE'],
            ['code' => 'organization.purch_org.view', 'name' => 'مشاهده سازمان خرید', 'module_name' => 'سازمان', 'action_type' => 'READ'],
            ['code' => 'organization.purch_org.manage', 'name' => 'مدیریت سازمان خرید', 'module_name' => 'سازمان', 'action_type' => 'EXECUTE'],
            ['code' => 'organization.intercompany.view', 'name' => 'مشاهده نقشه بین‌شرکتی', 'module_name' => 'سازمان', 'action_type' => 'READ'],
            ['code' => 'organization.intercompany.manage', 'name' => 'مدیریت نقشه و قوانین بین‌شرکتی', 'module_name' => 'سازمان', 'action_type' => 'EXECUTE'],

            // P6 consolidation / ESC
            ['code' => 'organization.consolidation.view', 'name' => 'مشاهده اجرای تلفیق', 'module_name' => 'سازمان', 'action_type' => 'READ'],
            ['code' => 'organization.consolidation.manage', 'name' => 'مدیریت اجرای تلفیق', 'module_name' => 'سازمان', 'action_type' => 'EXECUTE'],
            ['code' => 'organization.structure.configure', 'name' => 'پیکربندی ساختار سازمانی (ESC)', 'module_name' => 'سازمان', 'action_type' => 'EXECUTE'],
        ];
    }

    private function ensureOrganizationCatalog(string $tenantId): void
    {
        foreach ($this->organizationCatalog() as $perm) {
            $exists = DB::table('tenant_permissions')
                ->where('tenant_id', $tenantId)
                ->where('code', $perm['code'])
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('tenant_permissions')->insert([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'code' => $perm['code'],
                'name' => $perm['name'],
                'module_name' => $perm['module_name'],
                'action_type' => $perm['action_type'],
                'description' => $perm['name'],
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    private function mapCodes(string $tenantId, array $codes): array
    {
        $map = [];
        $rows = DB::table('tenant_permissions')
            ->where('tenant_id', $tenantId)
            ->whereIn('code', $codes)
            ->whereNull('deleted_at')
            ->get(['code', 'tenant_permission_id']);
        foreach ($rows as $row) {
            $map[$row->code] = $row->tenant_permission_id;
        }
        return $map;
    }

    private function bootstrapMinimalCatalog(string $tenantId): void
    {
        $minimal = [
            ['code' => 'identity.user.view', 'name' => 'مشاهده کاربران', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ'],
            ['code' => 'identity.user.create', 'name' => 'ایجاد کاربر', 'module_name' => 'هویت و دسترسی', 'action_type' => 'CREATE'],
            ['code' => 'identity.user.update', 'name' => 'ویرایش کاربر', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE'],
            ['code' => 'identity.user.delete', 'name' => 'حذف کاربر', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE'],
            ['code' => 'identity.role.view', 'name' => 'مشاهده نقش‌ها', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ'],
            ['code' => 'identity.role.create', 'name' => 'ایجاد نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'CREATE'],
            ['code' => 'identity.role.update', 'name' => 'ویرایش نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE'],
            ['code' => 'identity.role.delete', 'name' => 'حذف نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE'],
            ['code' => 'identity.role.assign', 'name' => 'تخصیص نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.role.assign-permissions', 'name' => 'تخصیص مجوز به نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.permission.view', 'name' => 'مشاهده مجوزها', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ'],
            ['code' => 'identity.scope.view', 'name' => 'مشاهده محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ'],
            ['code' => 'identity.scope.create', 'name' => 'ایجاد محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'CREATE'],
            ['code' => 'identity.scope.update', 'name' => 'ویرایش محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE'],
            ['code' => 'identity.scope.delete', 'name' => 'حذف محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE'],
            ['code' => 'identity.scope.assign', 'name' => 'تخصیص محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.profile.view', 'name' => 'مشاهده پروفایل', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ'],
            ['code' => 'identity.profile.update', 'name' => 'ویرایش پروفایل', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE'],
            ['code' => 'identity.membership_history.view', 'name' => 'مشاهده تاریخچه عضویت', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ'],
        ];

        foreach (array_merge($minimal, $this->organizationCatalog()) as $perm) {
            $exists = DB::table('tenant_permissions')
                ->where('tenant_id', $tenantId)
                ->where('code', $perm['code'])
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('tenant_permissions')->insert([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'code' => $perm['code'],
                'name' => $perm['name'],
                'module_name' => $perm['module_name'],
                'action_type' => $perm['action_type'],
                'description' => $perm['name'],
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $sourcePerms
     * @return array<string, string> code => permission_id
     */
    private function syncPermissions(string $tenantId, $sourcePerms): array
    {
        $map = [];
        foreach ($sourcePerms as $perm) {
            $existing = DB::table('tenant_permissions')
                ->where('tenant_id', $tenantId)
                ->where('code', $perm->code)
                ->first();

            if ($existing) {
                DB::table('tenant_permissions')->where('tenant_permission_id', $existing->tenant_permission_id)->update([
                    'name' => $perm->name,
                    'module_name' => $perm->module_name,
                    'action_type' => $perm->action_type ?? null,
                    'description' => $perm->description ?? null,
                    'status' => 1,
                    'updated_at' => now(),
                ]);
                $map[$perm->code] = $existing->tenant_permission_id;
            } else {
                $id = (string) Str::uuid();
                DB::table('tenant_permissions')->insert([
                    'tenant_permission_id' => $id,
                    'tenant_id' => $tenantId,
                    'code' => $perm->code,
                    'name' => $perm->name,
                    'module_name' => $perm->module_name,
                    'action_type' => $perm->action_type ?? null,
                    'description' => $perm->description ?? null,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $map[$perm->code] = $id;
            }
        }

        return $map;
    }

    /** @param list<string> $permissionIds */
    private function ensureAdminRole(string $tenantId, array $permissionIds): string
    {
        $role = DB::table('tenant_roles')->where('tenant_id', $tenantId)->where('code', 'tenant-admin')->first();
        if ($role) {
            $roleId = $role->tenant_role_id;
            DB::table('tenant_roles')->where('tenant_role_id', $roleId)->update([
                'name' => 'مدیر سازمان',
                'description' => 'دسترسی کامل به تمام قابلیت‌های سازمان',
                'status' => 1,
                'updated_at' => now(),
            ]);
        } else {
            $roleId = (string) Str::uuid();
            DB::table('tenant_roles')->insert([
                'tenant_role_id' => $roleId,
                'tenant_id' => $tenantId,
                'code' => 'tenant-admin',
                'name' => 'مدیر سازمان',
                'description' => 'دسترسی کامل به تمام قابلیت‌های سازمان',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('tenant_role_permissions')->where('tenant_id', $tenantId)->where('tenant_role_id', $roleId)->delete();
        $rows = [];
        $seen = [];
        foreach ($permissionIds as $pid) {
            if (isset($seen[$pid])) {
                continue;
            }
            $seen[$pid] = true;
            $rows[] = [
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'tenant_role_id' => $roleId,
                'tenant_permission_id' => $pid,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($rows !== []) {
            DB::table('tenant_role_permissions')->insert($rows);
        }

        return $roleId;
    }

    private function assignAdminToOwners(string $tenantId, string $roleId): void
    {
        if (!Schema::hasTable('tenant_users') || !Schema::hasTable('tenant_user_roles')) {
            return;
        }

        $owners = DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('is_owner', true)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->pluck('user_id');

        foreach ($owners as $userId) {
            $exists = DB::table('tenant_user_roles')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('tenant_role_id', $roleId)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('tenant_user_roles')->insert([
                'tenant_user_role_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'tenant_role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** @param array<string, string> $codeToId */
    private function seedDemoRoles(string $tenantId, array $codeToId): void
    {
        $demoRoles = [
            ['code' => 'accountant', 'name' => 'حسابدار', 'description' => 'دسترسی به اسناد و حساب‌ها', 'permission_codes' => ['accounting.voucher.view', 'accounting.voucher.post', 'accounting.account.view', 'identity.user.view', 'identity.profile.view']],
            ['code' => 'sales', 'name' => 'فروش', 'description' => 'سفارش و فاکتور فروش', 'permission_codes' => ['procurement.sales-order.create', 'procurement.sales-order.view', 'identity.user.view', 'identity.profile.view']],
            ['code' => 'warehouse', 'name' => 'انباردار', 'description' => 'کالا و انبار', 'permission_codes' => ['inventory.item.view', 'inventory.warehouse.view', 'identity.user.view', 'identity.profile.view']],
            ['code' => 'hr-viewer', 'name' => 'مشاهده‌گر منابع انسانی', 'description' => 'مشاهده کاربران', 'permission_codes' => ['identity.user.view', 'identity.profile.view', 'identity.membership_history.view', 'identity.role.view']],
        ];

        foreach ($demoRoles as $def) {
            $existing = DB::table('tenant_roles')->where('tenant_id', $tenantId)->where('code', $def['code'])->first();
            if ($existing) {
                $roleId = $existing->tenant_role_id;
                DB::table('tenant_roles')->where('tenant_role_id', $roleId)->update([
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'status' => 1,
                    'updated_at' => now(),
                ]);
            } else {
                $roleId = (string) Str::uuid();
                DB::table('tenant_roles')->insert([
                    'tenant_role_id' => $roleId,
                    'tenant_id' => $tenantId,
                    'code' => 'tenant-admin' === $def['code'] ? 'tenant-admin' : $def['code'],
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('tenant_role_permissions')->where('tenant_id', $tenantId)->where('tenant_role_id', $roleId)->delete();
            $rows = [];
            foreach ($def['permission_codes'] as $code) {
                if (!isset($codeToId[$code])) {
                    continue;
                }
                $rows[] = [
                    'tenant_role_permission_id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'tenant_role_id' => $roleId,
                    'tenant_permission_id' => $codeToId[$code],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($rows !== []) {
                DB::table('tenant_role_permissions')->insert($rows);
            }
        }
    }
}
