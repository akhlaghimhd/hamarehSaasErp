<?php

namespace Database\Seeders\SaasAdmin;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Local QA platform admin.
 * username: platform.admin
 * password: Admin123!
 */
class PlatformAdminSeeder extends Seeder
{
    private const USERNAME = 'platform.admin';
    private const EMAIL = 'admin@platform.local';
    private const PASSWORD = 'Admin123!';

    public function run(): void
    {
        if (! Schema::hasTable('admin_users')) {
            $this->command?->error('admin_users missing — run migrations first.');

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
            ->where('username', self::USERNAME)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            $adminId = (string) $existing->admin_user_id;
            DB::table('admin_users')->where('admin_user_id', $adminId)->update([
                'email'              => self::EMAIL,
                'password_hash'      => Hash::make(self::PASSWORD),
                'status'             => 1,
                'failed_login_count' => 0,
                'locked_until'       => null,
                'updated_at'         => now(),
            ]);
        } else {
            $adminId = (string) Str::uuid();
            DB::table('admin_users')->insert([
                'admin_user_id'      => $adminId,
                'username'           => self::USERNAME,
                'email'              => self::EMAIL,
                'password_hash'      => Hash::make(self::PASSWORD),
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
        $this->command?->info('  username: '.self::USERNAME);
        $this->command?->info('  password: '.self::PASSWORD);
        $this->command?->info('  login: POST /api/v1/saas-admin/auth/login');
    }
}
