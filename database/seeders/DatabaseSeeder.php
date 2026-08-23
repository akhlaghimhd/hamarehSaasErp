<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,        // اول ارزها
            TenantSeeder::class,          // دوم ساخت مستأجر سیستمی و دمو
            PermissionSeeder::class,      // سوم مجوزها و نقش tenant-admin برای دمو
            PlatformSettingSeeder::class, // چهارم تنظیمات پلتفرم
            AdminUserSeeder::class,       // پنجم سوپرادمین پلتفرم
        ]);
    }
}