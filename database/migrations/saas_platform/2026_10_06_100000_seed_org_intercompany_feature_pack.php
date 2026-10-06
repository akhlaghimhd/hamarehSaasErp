<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SAASADM-P0 — Ensure org.intercompany exists in platform_feature_catalog.
 * Idempotent: skips insert when code already present (including soft-deleted rows by code).
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('platform_feature_catalog')
            ->where('code', 'org.intercompany')
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return;
        }

        $now = now();

        DB::table('platform_feature_catalog')->insert([
            'feature_id'  => (string) Str::uuid(),
            'code'        => 'org.intercompany',
            'name'        => 'بین‌شرکتی',
            'description' => 'شرکای بین‌شرکتی و قواعد IC در لایه سازمان',
            'category'    => 'organization',
            'is_sellable' => true,
            'is_active'   => true,
            'sort_order'  => 50,
            'created_at'  => $now,
            'updated_at'  => $now,
            'row_version' => 1,
        ]);
    }

    public function down(): void
    {
        DB::table('platform_feature_catalog')
            ->where('code', 'org.intercompany')
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
    }
};
