<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ensures identity.privileged.* permission codes exist for every tenant.
 * Invoked from PermissionSeeder or callable standalone.
 */
final class PrivilegedPermissionBootstrap
{
    public static function codes(): array
    {
        return [
            ['code' => 'identity.privileged.view', 'name' => 'مشاهده دسترسی اضطراری', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ'],
            ['code' => 'identity.privileged.request', 'name' => 'درخواست دسترسی اضطراری', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.privileged.approve', 'name' => 'تأیید دسترسی اضطراری', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE'],
        ];
    }

    public static function ensureForTenant(string $tenantId): void
    {
        foreach (self::codes() as $perm) {
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
}
