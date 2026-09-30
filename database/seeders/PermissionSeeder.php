<?php

namespace Database\Seeders;

use App\Modules\IdentityCore\Services\TenantRbacBootstrapService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ensures permission catalog + tenant-admin, then default system roles + SoD.
 * RBAC roles via TenantRbacBootstrapService so tenants are not empty on first login.
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
            $this->ensureCorePerms((string) $sourceTenantId);
        } else {
            $sourceTenantId = (string) $sourceTenantId;
        }

        $sourcePerms = DB::table('tenant_permissions')
            ->where('tenant_id', $sourceTenantId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($tenantIds as $tenantId) {
            DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);
            $this->ensureCorePerms($tenantId);
            $map = $this->syncFromSource($tenantId, $sourcePerms);
            $this->ensureAdminRole($tenantId, array_values($map));
            $this->assignAdminToOwners($tenantId);

            try {
                $r = app(TenantRbacBootstrapService::class)->bootstrapTenant($tenantId, false);
                $this->command?->info(
                    "Tenant {$tenantId}: perms=".count($map)
                    ." roles+={$r['roles_created']} ~={$r['roles_updated']} sod={$r['sod_created']}"
                );
            } catch (\Throwable $e) {
                $this->command?->warn("Tenant {$tenantId}: bootstrap failed: ".$e->getMessage());
            }
        }
    }

    private function coreCodes(): array
    {
        return [
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
            ['code' => 'identity.sod.view', 'name' => 'مشاهده قوانین تفکیک وظایف', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ'],
            ['code' => 'identity.sod.manage', 'name' => 'مدیریت قوانین تفکیک وظایف', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.system_notification.receive', 'name' => 'دریافت پیام‌های سیستمی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.mfa.manage', 'name' => 'مدیریت احراز هویت چندمرحله‌ای', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.access_cert.view', 'name' => 'مشاهده گواهی دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ'],
            ['code' => 'identity.access_cert.manage', 'name' => 'مدیریت کمپین گواهی دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.access_cert.certify', 'name' => 'تصمیم‌گیری گواهی دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.access_cert.receive_reminder', 'name' => 'دریافت یادآوری بازبینی دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE'],
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
            ['code' => 'organization.hierarchy.view', 'name' => 'مشاهده سلسله‌مراتب سازمانی', 'module_name' => 'سازمان', 'action_type' => 'READ'],
            ['code' => 'organization.hierarchy.manage', 'name' => 'مدیریت سلسله‌مراتب سازمانی', 'module_name' => 'سازمان', 'action_type' => 'EXECUTE'],
        ];
    }

    private function ensureCorePerms(string $tenantId): void
    {
        foreach ($this->coreCodes() as $perm) {
            if (DB::table('tenant_permissions')->where('tenant_id', $tenantId)->where('code', $perm['code'])->exists()) {
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

    private function syncFromSource(string $tenantId, $sourcePerms): array
    {
        $map = [];
        foreach ($sourcePerms as $perm) {
            $existing = DB::table('tenant_permissions')->where('tenant_id', $tenantId)->where('code', $perm->code)->first();
            if ($existing) {
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
        foreach (DB::table('tenant_permissions')->where('tenant_id', $tenantId)->whereNull('deleted_at')->get(['code', 'tenant_permission_id']) as $row) {
            $map[$row->code] = $row->tenant_permission_id;
        }
        return $map;
    }

    private function ensureAdminRole(string $tenantId, array $permissionIds): string
    {
        $role = DB::table('tenant_roles')->where('tenant_id', $tenantId)->where('code', 'tenant-admin')->first();
        if ($role) {
            $roleId = $role->tenant_role_id;
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
            $pid = (string) $pid;
            if ($pid === '' || isset($seen[$pid])) {
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
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('tenant_role_permissions')->insert($chunk);
        }
        return $roleId;
    }

    private function assignAdminToOwners(string $tenantId): void
    {
        $roleId = DB::table('tenant_roles')->where('tenant_id', $tenantId)->where('code', 'tenant-admin')->value('tenant_role_id');
        if (!$roleId) {
            return;
        }
        $owners = DB::table('tenant_users')->where('tenant_id', $tenantId)->where('is_owner', true)->whereNull('deleted_at')->pluck('user_id');
        foreach ($owners as $userId) {
            $exists = DB::table('tenant_user_roles')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('tenant_role_id', $roleId)
                ->whereNull('deleted_at')
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
}
