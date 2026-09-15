<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Do not overwrite admin_role_id on existing SUPER_ADMIN rows — FK from
        // admin_role_permissions would break (SQLSTATE 23503).
        $existingRole = DB::table('admin_roles')->where('code', 'SUPER_ADMIN')->first();

        if ($existingRole) {
            DB::table('admin_roles')
                ->where('code', 'SUPER_ADMIN')
                ->update([
                    'name'        => 'Super Admin',
                    'description' => 'دسترسی کامل به تمامی بخش‌های پلتفرم SaaS',
                    'status'      => 1,
                    'updated_at'  => now(),
                ]);
            $roleId = $existingRole->admin_role_id;
        } else {
            $roleId = '4b365116-44c8-45e0-b54e-b61d6aedcc64';
            DB::table('admin_roles')->insert([
                'admin_role_id' => $roleId,
                'code'          => 'SUPER_ADMIN',
                'name'          => 'Super Admin',
                'description'   => 'دسترسی کامل به تمامی بخش‌های پلتفرم SaaS',
                'status'        => 1,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        DB::table('admin_users')->updateOrInsert(
            ['username' => 'superadmin'],
            [
                'admin_user_id'      => '11111111-1111-1111-1111-111111111111',
                'username'           => 'superadmin',
                'email'              => 'admin@hamareh.com',
                'password_hash'      => Hash::make('secret123'),
                'first_name'         => 'مدیر',
                'last_name'          => 'کل پلتفرم',
                'mobile'             => '09120000000',
                'status'             => 1,
                'two_factor_enabled' => false,
                'updated_at'         => now(),
                'created_at'         => now(),
            ]
        );
    }
}
