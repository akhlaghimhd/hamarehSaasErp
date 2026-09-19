<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates a tenant owner for local QA.
 * Email: owner@demo.local  Password: Owner123!
 *
 * Usage:
 *   docker compose exec app php artisan db:seed --class=DemoTenantOwnerSeeder
 */
class DemoTenantOwnerSeeder extends Seeder
{
    private const EMAIL = 'owner@demo.local';
    private const PASSWORD = 'Owner123!';
    private const MOBILE = '09121111111';

    public function run(): void
    {
        $tenantId = $this->resolveTenantId();
        if ($tenantId === null) {
            $this->command?->error('No tenant found. Seed tenants first.');
            return;
        }

        // Bypass RLS for seeder writes on tenant_* tables
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

        $userId = $this->upsertUser();
        $this->upsertCredential($userId);
        $this->upsertTenantMembership($tenantId, $userId);
        $this->assignTenantAdminRole($tenantId, $userId);

        $this->command?->info('Demo tenant owner ready:');
        $this->command?->info('  email:    '.self::EMAIL);
        $this->command?->info('  password: '.self::PASSWORD);
        $this->command?->info('  tenant:   '.$tenantId);
        $this->command?->info('  user_id:  '.$userId);
    }

    private function resolveTenantId(): ?string
    {
        $fromEnv = env('DEMO_TENANT_ID');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }

        return DB::table('tenants')
            ->orderBy('created_at')
            ->value('tenant_id');
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

    private function assignTenantAdminRole(string $tenantId, string $userId): void
    {
        $roleId = DB::table('tenant_roles')
            ->where('tenant_id', $tenantId)
            ->where('code', 'tenant-admin')
            ->whereNull('deleted_at')
            ->value('tenant_role_id');

        if (!$roleId) {
            $this->command?->warn('Role tenant-admin not found for this tenant; owner flag alone is enough for full permissions on login.');

            return;
        }

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
