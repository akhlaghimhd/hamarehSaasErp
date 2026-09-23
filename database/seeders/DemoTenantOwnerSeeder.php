<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Creates a tenant owner for local QA on the same demo tenant as PermissionSeeder.
 * Email: owner@demo.local  Password: Owner123!
 *
 * Also ensures the primary organization company exists (onboarding parity):
 * when a customer becomes a platform tenant, their legal company should already
 * appear under Organization — not require a second "create company" step.
 *
 * Usage:
 *   docker compose exec app php artisan db:seed --class=PermissionSeeder
 *   docker compose exec app php artisan db:seed --class=DemoTenantOwnerSeeder
 */
class DemoTenantOwnerSeeder extends Seeder
{
    /** Must match PermissionSeeder / TenantSeeder demo tenant. */
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
        $companyId = $this->ensurePrimaryCompany($tenantId);

        $this->command?->info('Demo tenant owner ready:');
        $this->command?->info('  email:       '.self::EMAIL);
        $this->command?->info('  password:    '.self::PASSWORD);
        $this->command?->info('  tenant_id:   '.$tenantId);
        $this->command?->info('  user_id:     '.$userId);
        $this->command?->info('  company_id:  '.($companyId ?? 'n/a'));
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

    /**
     * Primary company = legal entity registered at tenant onboarding (ORG-P1-06).
     * Idempotent: promote oldest active company to primary; else create HQ OPERATING.
     */
    private function ensurePrimaryCompany(string $tenantId): ?string
    {
        if (!Schema::hasTable('erp_companies')) {
            $this->command?->warn('erp_companies table missing — skip primary company seed.');

            return null;
        }

        $hasIsPrimary = Schema::hasColumn('erp_companies', 'is_primary');
        $hasEntityKind = Schema::hasColumn('erp_companies', 'entity_kind');
        $hasLegalName = Schema::hasColumn('erp_companies', 'legal_name');

        $existing = DB::table('erp_companies')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->first();

        if ($existing) {
            $update = ['updated_at' => now()];
            if ($hasIsPrimary) {
                // Clear other primaries then set this one
                DB::table('erp_companies')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where('company_id', '!=', $existing->company_id)
                    ->update(['is_primary' => false]);
                $update['is_primary'] = true;
            }
            if ($hasEntityKind && empty($existing->entity_kind)) {
                $update['entity_kind'] = 'OPERATING';
            }
            if ($hasLegalName && empty($existing->legal_name)) {
                $update['legal_name'] = $existing->name;
            }
            DB::table('erp_companies')->where('company_id', $existing->company_id)->update($update);

            return (string) $existing->company_id;
        }

        $tenantName = (string) (DB::table('tenants')->where('tenant_id', $tenantId)->value('tenant_name') ?? 'شرکت اصلی');
        $companyId = (string) Str::uuid();

        $row = [
            'company_id' => $companyId,
            'tenant_id' => $tenantId,
            'code' => 'HQ',
            'name' => $tenantName,
            'registration_number' => null,
            'economic_code' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'row_version' => 1,
        ];

        if ($hasLegalName) {
            $row['legal_name'] = $tenantName;
        }
        if ($hasIsPrimary) {
            $row['is_primary'] = true;
        }
        if ($hasEntityKind) {
            $row['entity_kind'] = 'OPERATING';
        }
        if (Schema::hasColumn('erp_companies', 'status')) {
            $row['status'] = 1;
        }

        DB::table('erp_companies')->insert($row);

        $this->command?->info("Primary company seeded: {$tenantName} (HQ / {$companyId})");

        return $companyId;
    }
}
