<?php

namespace Database\Seeders\SaasAdmin;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Bootstrap platform owner admin from environment (SAASADM-P2-06).
 *
 * Required env (production):
 *   PLATFORM_ADMIN_USERNAME
 *   PLATFORM_ADMIN_EMAIL
 *   PLATFORM_ADMIN_PASSWORD
 *
 * Local defaults only apply when APP_ENV=local|testing and env vars are unset.
 */
class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('admin_users')) {
            $this->command?->error('admin_users missing — run migrations first.');

            return;
        }

        $username = (string) env('PLATFORM_ADMIN_USERNAME', '');
        $email = (string) env('PLATFORM_ADMIN_EMAIL', '');
        $password = (string) env('PLATFORM_ADMIN_PASSWORD', '');

        $isLocal = in_array(app()->environment(), ['local', 'testing'], true);

        if ($username === '' || $email === '' || $password === '') {
            if (! $isLocal) {
                $this->command?->warn(
                    'PlatformAdminSeeder skipped: set PLATFORM_ADMIN_USERNAME, PLATFORM_ADMIN_EMAIL, PLATFORM_ADMIN_PASSWORD.'
                );

                return;
            }

            // Local/testing fallback only — never used in production.
            $username = $username !== '' ? $username : 'platform.admin';
            $email = $email !== '' ? $email : 'admin@platform.local';
            $password = $password !== '' ? $password : 'Admin123!';
            $this->command?->warn(
                'PlatformAdminSeeder using local defaults (APP_ENV='.app()->environment().'). Set PLATFORM_ADMIN_* for real deploys.'
            );
        }

        if (strlen($password) < 10) {
            $this->command?->error('PLATFORM_ADMIN_PASSWORD must be at least 10 characters.');

            return;
        }

        $this->call(AdminPermissionSeeder::class);

        $roleId = DB::table('admin_roles')
            ->where('code', 'SUPER_ADMIN')
            ->whereNull('deleted_at')
            ->value('admin_role_id');

        if (! $roleId) {
            $this->command?->error('SUPER_ADMIN role missing after AdminPermissionSeeder.');

            return;
        }

        $existing = DB::table('admin_users')
            ->where('username', $username)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            $adminId = (string) $existing->admin_user_id;
            DB::table('admin_users')->where('admin_user_id', $adminId)->update([
                'email'              => $email,
                'password_hash'      => Hash::make($password),
                'status'             => 1,
                'failed_login_count' => 0,
                'locked_until'       => null,
                'updated_at'         => now(),
            ]);
        } else {
            $adminId = (string) Str::uuid();
            DB::table('admin_users')->insert([
                'admin_user_id'      => $adminId,
                'username'           => $username,
                'email'              => $email,
                'password_hash'      => Hash::make($password),
                'first_name'         => 'Platform',
                'last_name'          => 'Admin',
                'status'             => 1,
                'failed_login_count' => 0,
                'two_factor_enabled' => false,
                'created_at'         => now(),
                'updated_at'         => now(),
                'row_version'        => 1,
            ]);
        }

        $linked = DB::table('admin_user_roles')
            ->where('admin_user_id', $adminId)
            ->where('admin_role_id', $roleId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $linked) {
            DB::table('admin_user_roles')->insert([
                'admin_user_role_id' => (string) Str::uuid(),
                'admin_user_id'      => $adminId,
                'admin_role_id'      => $roleId,
                'created_at'         => now(),
                'updated_at'         => now(),
                'row_version'        => 1,
            ]);
        }

        $this->command?->info('Platform admin ready:');
        $this->command?->info('  username: '.$username);
        $this->command?->info('  email: '.$email);
        $this->command?->info('  password: (from PLATFORM_ADMIN_PASSWORD or local default — not printed)');
        $this->command?->info('  login: POST /api/v1/saas-admin/auth/login');
    }
}
