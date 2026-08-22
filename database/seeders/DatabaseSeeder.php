<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,        // اول ارزها
            TenantSeeder::class,          // دوم ساخت مستأجر سیستمی
            PlatformSettingSeeder::class,  // سوم تنظیمات پلتفرم
            AdminUserSeeder::class,       // چهارم سوپرادمین
        ]);
    }
}