<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Surgical fix so demo accounts can log in without org-picker failures:
 * 1) Ensure owner@demo.local password + active owner membership on demo tenant only
 * 2) Soft-delete extra memberships for owner on other tenants (e.g. System Root)
 * 3) Reactivate staff *@demo.local on demo tenant without unique violations
 * 4) Clear credential locks and purge tokens
 */
class ForceDemoLoginFixSeeder extends Seeder
{
    private const OWNER_EMAIL = 'owner@demo.local';
    private const OWNER_PASSWORD = 'Owner123!';
    private const STAFF_PASSWORD = 'Staff123!';
    private const DEMO_TENANT_ID = '3ab77cac-1343-4b13-8e14-0d887aad132a';

    public function run(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('tenant_users')) {
            $this->command?->warn('tables missing');

            return;
        }

        $demoTenantId = $this->resolveDemoTenantId();
        $this->command?->info('demo tenant: '.$demoTenantId);

        $owner = DB::table('users')->where('email', self::OWNER_EMAIL)->first();
        if (!$owner) {
            $ownerId = (string) Str::uuid();
            DB::table('users')->insert([
                'user_id' => $ownerId,
                'email' => self::OWNER_EMAIL,
                'mobile' => '09121111111',
                'first_name' => 'Owner',
                'last_name' => 'Demo',
                'status' => 1,
                'user_kind' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $ownerId = $owner->user_id;
            DB::table('users')->where('user_id', $ownerId)->update([
                'status' => 1,
                'deleted_at' => null,
                'updated_at' => now(),
            ]);
        }

        $hash = Hash::make(self::OWNER_PASSWORD);
        if (Schema::hasTable('user_credentials')) {
            $cred = DB::table('user_credentials')->where('user_id', $ownerId)->first();
            if ($cred) {
                DB::table('user_credentials')->where('user_id', $ownerId)->update([
                    'password_hash' => $hash,
                    'failed_login_count' => 0,
                    'locked_until' => null,
                    'must_set_password' => false,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('user_credentials')->insert([
                    'credential_id' => (string) Str::uuid(),
                    'user_id' => $ownerId,
                    'password_hash' => $hash,
                    'authentication_type' => 1,
                    'is_verified' => true,
                    'two_factor_enabled' => false,
                    'failed_login_count' => 0,
                    'must_set_password' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('tenant_users')
            ->where('user_id', $ownerId)
            ->where('tenant_id', '!=', $demoTenantId)
            ->whereNull('deleted_at')
            ->update([
                'status' => 0,
                'is_owner' => false,
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

        $demoMembership = DB::table('tenant_users')
            ->where('user_id', $ownerId)
            ->where('tenant_id', $demoTenantId)
            ->orderByRaw('deleted_at IS NULL DESC')
            ->orderByDesc('updated_at')
            ->first();

        if ($demoMembership) {
            DB::table('tenant_users')
                ->where('tenant_user_id', $demoMembership->tenant_user_id)
                ->update([
                    'status' => 1,
                    'is_owner' => true,
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);
            DB::table('tenant_users')
                ->where('user_id', $ownerId)
                ->where('tenant_id', $demoTenantId)
                ->where('tenant_user_id', '!=', $demoMembership->tenant_user_id)
                ->whereNull('deleted_at')
                ->update([
                    'status' => 0,
                    'is_owner' => false,
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('tenant_users')->insert([
                'tenant_user_id' => (string) Str::uuid(),
                'tenant_id' => $demoTenantId,
                'user_id' => $ownerId,
                'is_owner' => true,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $adminRole = DB::table('tenant_roles')
            ->where('tenant_id', $demoTenantId)
            ->where('code', 'tenant-admin')
            ->whereNull('deleted_at')
            ->first();
        if ($adminRole && Schema::hasTable('tenant_user_roles')) {
            $exists = DB::table('tenant_user_roles')
                ->where('tenant_id', $demoTenantId)
                ->where('user_id', $ownerId)
                ->where('tenant_role_id', $adminRole->tenant_role_id)
                ->exists();
            if (!$exists) {
                DB::table('tenant_user_roles')->insert([
                    'tenant_user_role_id' => (string) Str::uuid(),
                    'tenant_id' => $demoTenantId,
                    'user_id' => $ownerId,
                    'tenant_role_id' => $adminRole->tenant_role_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $staffEmails = DB::table('users')
            ->where('email', 'like', '%@demo.local')
            ->where('email', '!=', self::OWNER_EMAIL)
            ->whereNull('deleted_at')
            ->pluck('user_id', 'email');

        $staffHash = Hash::make(self::STAFF_PASSWORD);
        foreach ($staffEmails as $email => $userId) {
            DB::table('users')->where('user_id', $userId)->update([
                'status' => 1,
                'updated_at' => now(),
            ]);
            if (Schema::hasTable('user_credentials')) {
                $c = DB::table('user_credentials')->where('user_id', $userId)->first();
                if ($c) {
                    DB::table('user_credentials')->where('user_id', $userId)->update([
                        'password_hash' => $staffHash,
                        'failed_login_count' => 0,
                        'locked_until' => null,
                        'updated_at' => now(),
                    ]);
                }
            }

            $rows = DB::table('tenant_users')
                ->where('user_id', $userId)
                ->where('tenant_id', $demoTenantId)
                ->orderByRaw('deleted_at IS NULL DESC')
                ->orderByDesc('updated_at')
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }
            $keep = $rows->first();
            DB::table('tenant_users')->where('tenant_user_id', $keep->tenant_user_id)->update([
                'status' => 1,
                'deleted_at' => null,
                'updated_at' => now(),
            ]);
            foreach ($rows->skip(1) as $extra) {
                if ($extra->deleted_at === null) {
                    DB::table('tenant_users')->where('tenant_user_id', $extra->tenant_user_id)->update([
                        'status' => 0,
                        'is_owner' => false,
                        'deleted_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if (Schema::hasTable('user_credentials')) {
            DB::table('user_credentials')->update([
                'failed_login_count' => 0,
                'locked_until' => null,
            ]);
        }

        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')->delete();
        }

        $activeOwner = DB::table('tenant_users')
            ->where('user_id', $ownerId)
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->count();

        $this->command?->info("ForceDemoLoginFix done. owner active memberships={$activeOwner}");
        $this->command?->info('Login: '.self::OWNER_EMAIL.' / '.self::OWNER_PASSWORD);
        $this->command?->info('Staff: *@demo.local / '.self::STAFF_PASSWORD);
        $this->command?->info('Owner should land WITHOUT org picker (single membership).');
    }

    private function resolveDemoTenantId(): string
    {
        if (DB::table('tenants')->where('tenant_id', self::DEMO_TENANT_ID)->exists()) {
            return self::DEMO_TENANT_ID;
        }
        $bySlug = DB::table('tenants')->where('slug', 'demo')->whereNull('deleted_at')->value('tenant_id');
        if ($bySlug) {
            return (string) $bySlug;
        }
        $first = DB::table('tenants')->whereNull('deleted_at')->where('status', 1)->orderBy('created_at')->value('tenant_id');
        if (!$first) {
            throw new \RuntimeException('No tenant found');
        }

        return (string) $first;
    }
}
