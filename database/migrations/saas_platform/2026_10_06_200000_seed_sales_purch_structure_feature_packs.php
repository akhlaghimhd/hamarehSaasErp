<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DEBT-ORG-003 / H3 — sellable packs for Sales and Purchasing structure masters.
 * Idempotent per code.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [
            [
                'code'        => 'org.sales_structure',
                'name'        => 'ساختار فروش',
                'description' => 'سازمان فروش، کانال، دیویژن، ناحیه، دفتر و گروه فروش',
                'sort_order'  => 60,
            ],
            [
                'code'        => 'org.purch_structure',
                'name'        => 'ساختار خرید',
                'description' => 'سازمان خرید و تخصیص‌های خرید',
                'sort_order'  => 70,
            ],
        ];

        foreach ($rows as $row) {
            $exists = DB::table('platform_feature_catalog')
                ->where('code', $row['code'])
                ->whereNull('deleted_at')
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('platform_feature_catalog')->insert([
                'feature_id'  => (string) Str::uuid(),
                'code'        => $row['code'],
                'name'        => $row['name'],
                'description' => $row['description'],
                'category'    => 'organization',
                'is_sellable' => true,
                'is_active'   => true,
                'sort_order'  => $row['sort_order'],
                'created_at'  => $now,
                'updated_at'  => $now,
                'row_version' => 1,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('platform_feature_catalog')
            ->whereIn('code', ['org.sales_structure', 'org.purch_structure'])
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
    }
};
