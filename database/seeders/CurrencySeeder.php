<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        // تشخیص هوشمند نام جدول (با اسکیما یا بدون اسکیما)
        $tableName = Schema::hasTable('platform.currencies') ? 'platform.currencies' : 'currencies';

        DB::table($tableName)->updateOrInsert(
            ['code' => 'IRR'],
            [
                'name'       => 'ریال ایران',
                'symbol'     => 'ریال',
                'is_default' => true,
                'status'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table($tableName)->updateOrInsert(
            ['code' => 'USD'],
            [
                'name'       => 'دلار آمریکا',
                'symbol'     => '$',
                'is_default' => false,
                'status'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}