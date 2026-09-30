<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * ID-W2-01 — ensure access certification permission codes exist for all tenants,
 * attach them to tenant-admin, and ensure active owners hold tenant-admin.
 */
class AccessCertPermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('tenant_permissions') || !Schema::hasTable('tenants')) {
            $this->command?->error('Required tables missing.');

            return;
        }

        $perms = [
            ['code' => 'identity.access_cert.view', 'name' => 'مشاهده گواهی دسترسی', 'action_type' => 'READ'],
            ['code' => 'identity.access_cert.manage', 'name' => 'مدیریت کمپین گواهی دسترسی', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.access_cert.certify', 'name' => 'تصمیم‌گیری گواهی دسترسی', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.access_cert.receive_reminder', 'name' => 'دریافت یادآوری بازبینی دسترسی', 'action_type' => 'EXECUTE'],
        ];

        $tenantIds = DB::table('tenants')->pluck('tenant_id')->map(fn ($id) => (string) $id)->all();
        foreach ($tenantIds as $tenantId) {
            DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

            foreach ($perms as $perm) {
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
                    'module_name' => 'هویت و دسترسی',
                    'action_type' => $perm['action_type'],
                    'description' => $perm['name'],
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $roleQ = DB::table('tenant_roles')
                ->where('tenant_id', $tenantId)
                ->where('code', 'tenant-admin');
            if (Schema::hasColumn('tenant_roles', 'deleted_at')) {
                $roleQ->whereNull('deleted_at');
            }
            $roleId = $roleQ->value('tenant_role_id');

            if (!$roleId) {
                $this->command?->warn("Tenant {$tenantId}: no tenant-admin role — skip attach");
                continue;
            }

            if (Schema::hasTable('tenant_role_permissions')) {
                $permQ = DB::table('tenant_permissions')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('code', array_column($perms, 'code'));
                if (Schema::hasColumn('tenant_permissions', 'deleted_at')) {
                    $permQ->whereNull('deleted_at');
                }
                $permIds = $permQ->pluck('tenant_permission_id');
                $attached = 0;
                foreach ($permIds as $pid) {
                    $existsQ = DB::table('tenant_role_permissions')
                        ->where('tenant_id', $tenantId)
                        ->where('tenant_role_id', $roleId)
                        ->where('tenant_permission_id', $pid);
                    if (Schema::hasColumn('tenant_role_permissions', 'deleted_at')) {
                        $existsQ->whereNull('deleted_at');
                    }
                    if ($existsQ->exists()) {
                        continue;
                    }
                    DB::table('tenant_role_permissions')->insert([
                        'tenant_role_permission_id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'tenant_role_id' => $roleId,
                        'tenant_permission_id' => $pid,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $attached++;
                }
                $this->command?->info("Tenant {$tenantId}: tenant-admin perm links +{$attached}");
            }

            // Ensure active owners hold tenant-admin so they receive reminders.
            if (Schema::hasTable('tenant_user_roles') && Schema::hasTable('tenant_users')) {
                $ownersQ = DB::table('tenant_users')
                    ->where('tenant_id', $tenantId)
                    ->where('is_owner', true)
                    ->where('status', 1);
                if (Schema::hasColumn('tenant_users', 'deleted_at')) {
                    $ownersQ->whereNull('deleted_at');
                }
                $owners = $ownersQ->pluck('user_id');
                $assigned = 0;
                foreach ($owners as $userId) {
                    $existsQ = DB::table('tenant_user_roles')
                        ->where('tenant_id', $tenantId)
                        ->where('user_id', $userId)
                        ->where('tenant_role_id', $roleId);
                    if (Schema::hasColumn('tenant_user_roles', 'deleted_at')) {
                        $existsQ->whereNull('deleted_at');
                    }
                    if ($existsQ->exists()) {
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
                    $assigned++;
                }
                $this->command?->info("Tenant {$tenantId}: owners→tenant-admin +{$assigned}");
            }
        }
    }
}
