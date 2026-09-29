<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** ID-W2-01 — ensure access certification permission codes exist for all tenants. */
class AccessCertPermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('tenant_permissions') || !Schema::hasTable('tenants')) {
            return;
        }

        $perms = [
            ['code' => 'identity.access_cert.view', 'name' => 'مشاهده گواهی دسترسی', 'action_type' => 'READ'],
            ['code' => 'identity.access_cert.manage', 'name' => 'مدیریت کمپین گواهی دسترسی', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.access_cert.certify', 'name' => 'تصمیم‌گیری گواهی دسترسی', 'action_type' => 'EXECUTE'],
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
        }
    }
}
