<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // ۱. درج یا به‌روزرسانی نقش سوپرادمین بر اساس کد نقش (code)
        $roleId = '4b365116-44c8-45e0-b54e-b61d6aedcc64';
        
        DB::table('admin_roles')->updateOrInsert(
            ['code' => 'SUPER_ADMIN'], // ستون معتبر برای جستجو
            [
                'admin_role_id' => $roleId,
                'name'          => 'Super Admin',
                'description'   => 'دسترسی کامل به تمامی بخش‌های پلتفرم SaaS',
                'status'        => 1,
                'updated_at'    => now(),
                'created_at'    => now(),
            ]
        );

        // ۲. درج کاربر سوپرادمین
        DB::table('admin_users')->updateOrInsert(
            ['username' => 'superadmin'],
            [
                'admin_user_id'     => '11111111-1111-1111-1111-111111111111',
                'username'          => 'superadmin',
                'email'             => 'admin@hamareh.com',
                'password_hash'     => Hash::make('secret123'),
                'first_name'        => 'مدیر',
                'last_name'         => 'کل پلتفرم',
                'mobile'            => '09120000000',
                'status'            => 1,
                'two_factor_enabled'=> false,
                'updated_at'        => now(),
                'created_at'        => now(),
            ]
        );
    }
}