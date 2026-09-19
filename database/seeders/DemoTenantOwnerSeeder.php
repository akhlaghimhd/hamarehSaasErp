<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates a tenant owner for local QA on the same demo tenant as PermissionSeeder.
 * Email: owner@demo.local  Password: Owner123!
 *
 * Usage:
 *   docker compose exec app php artisan db:seed --class=PermissionSeeder
 *   docker compose exec app php artisan db:seed --class=DemoTenantOwnerSeeder
 */
class DemoTenantOwnerSeeder extends Seeder
{
    /** Must match PermissionSeeder demo tenant. */
    private const DEMO_TENANT_ID = '3ab77cac-1343-4b13-8e14-0d887aad132a';

    private const EMAIL = 'owner@demo.local';
    private const PASSWORD = 'Owner123!';
    private const MOBILE = '09121111111';

    public function run(): void
    {
        $tenantId = $this->resolveTenantId();
        if ($tenantId === null) {
            $this->command?->error(
                'Demo tenant not found. Create tenant '.self::DEMO_TENANT_ID.' or set DEMO_TENANT_ID in .env to an existing tenant_id, then re-run PermissionSeeder.'
            );

            return;
        }

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

        $permCount = (int) DB::table('tenant_permissions')
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->count();

        if ($permCount === 0) {
            $this->command?->warn('No active permissions for this tenant. Running PermissionSeeder first is required.');
            $this->command?->warn('  docker compose exec app php artisan db:seed --class=PermissionSeeder');
        }

        $userId = $this->upsertUser();
        $this->upsertCredential($userId);
        $this->upsertTenantMembership($tenantId, $userId);
        $roleId = $this->ensureTenantAdminRole($tenantId);
        $this->assignRole($tenantId, $userId, $roleId);

        $this->command?->info('Demo tenant owner ready:');
        $this->command?->info('  email:       '.self::EMAIL);
        $this->command?->info('  password:    '.self::PASSWORD);
        $this->command?->info('  tenant_id:   '.$tenantId);
        $this->command?->info('  user_id:     '.$userId);
        $this->command?->info('  permissions: '.$permCount.' active codes on tenant');
        $this->command?->info('Re-login is required after seed so the token picks up permissions.');
    }

    private function resolveTenantId(): ?string
    {
        $fromEnv = env('DEMO_TENANT_ID');
        if (is_string($fromEnv) && $fromEnv !== '') {
            $exists = DB::table('tenants')->where('tenant_id', $fromEnv)->exists();

            return $exists ? $fromEnv : null;
        }

        if (DB::table('tenants')->where('tenant_id', self::DEMO_TENANT_ID)->exists()) {
            return self::DEMO_TENANT_ID;
        }

        // Fallback: first tenant (still may lack seeded permissions)
        return DB::table('tenants')->orderBy('created_at')->value('tenant_id');
    }

    private function upsertUser(): string
    {
        $existing = DB::table('users')->where('email', self::EMAIL)->first();
        if ($existing) {
            DB::table('users')->where('user_id', $existing->user_id)->update([
                'mobile' => self::MOBILE,
                'first_name' => 'Owner',
                'last_name' => 'Demo',
                'status' => 1,
                'updated_at' => now(),
            ]);

            return (string) $existing->user_id;
        }

        $userId = (string) Str::uuid();
        DB::table('users')->insert([
            'user_id' => $userId,
            'email' => self::EMAIL,
            'mobile' => self::MOBILE,
            'first_name' => 'Owner',
            'last_name' => 'Demo',
            'user_kind' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'row_version' => 1,
        ]);

        return $userId;
    }

    private function upsertCredential(string $userId): void
    {
        $hash = Hash::make(self::PASSWORD);
        $existing = DB::table('user_credentials')->where('user_id', $userId)->first();

        if ($existing) {
            DB::table('user_credentials')->where('credential_id', $existing->credential_id)->update([
                'password_hash' => $hash,
                'authentication_type' => 1,
                'is_verified' => true,
                'failed_login_count' => 0,
                'locked_until' => null,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('user_credentials')->insert([
            'credential_id' => (string) Str::uuid(),
            'user_id' => $userId,
            'password_hash' => $hash,
            'authentication_type' => 1,
            'is_verified' => true,
            'two_factor_enabled' => false,
            'failed_login_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
            'row_version' => 1,
        ]);
    }

    private function upsertTenantMembership(string $tenantId, string $userId): void
    {
        $existing = DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            DB::table('tenant_users')->where('tenant_user_id', $existing->tenant_user_id)->update([
                'is_owner' => true,
                'status' => 1,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('tenant_users')->insert([
            'tenant_user_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'is_owner' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'row_version' => 1,
        ]);
    }

    private function ensureTenantAdminRole(string $tenantId): string
    {
        $existing = DB::table('tenant_roles')
            ->where('tenant_id', $tenantId)
            ->where('code', 'tenant-admin')
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            DB::table('tenant_roles')->where('tenant_role_id', $existing->tenant_role_id)->update([
                'status' => 1,
                'updated_at' => now(),
            ]);

            return (string) $existing->tenant_role_id;
        }

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
            'row_version' => 1,
        ]);

        return $roleId;
    }

    private function assignRole(string $tenantId, string $userId, string $roleId): void
    {
        $exists = DB::table('tenant_user_roles')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('tenant_role_id', $roleId)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('tenant_user_roles')->insert([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'tenant_role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
            'row_version' => 1,
        ]);
    }
}
