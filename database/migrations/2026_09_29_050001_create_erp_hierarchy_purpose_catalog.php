<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * D6 — Extensible hierarchy purpose catalog (platform master data, no tenant_id).
 * SYS trees stay limited; catalog labels/types can grow without code enum churn.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('erp_hierarchy_purpose_catalog')) {
            Schema::create('erp_hierarchy_purpose_catalog', function (Blueprint $table) {
                $table->uuid('purpose_id')->primary();
                $table->string('code', 30);
                $table->string('label_fa', 100);
                $table->string('label_en', 100)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->boolean('allows_user_tree')->default(true);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(100);
                $table->jsonb('allowed_entity_types')->nullable();
                $table->unsignedBigInteger('row_version')->default(1);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['code'], 'uq_erp_hierarchy_purpose_catalog_code');
                $table->index(['is_active', 'sort_order'], 'idx_erp_hierarchy_purpose_catalog_active');
            });
        }

        $now = now();
        $rows = [
            [
                'code' => 'LEGAL',
                'label_fa' => 'حقوقی',
                'label_en' => 'Legal',
                'description' => 'ساختار حقوقی شرکت‌ها و مالکیت',
                'is_system' => true,
                'allows_user_tree' => false,
                'sort_order' => 10,
                'allowed_entity_types' => json_encode(['COMPANY']),
            ],
            [
                'code' => 'ESTABLISHMENT',
                'label_fa' => 'استقرار',
                'label_en' => 'Establishment',
                'description' => 'استقرار شعب زیر شرکت',
                'is_system' => true,
                'allows_user_tree' => false,
                'sort_order' => 20,
                'allowed_entity_types' => json_encode(['COMPANY', 'BRANCH']),
            ],
            [
                'code' => 'MANAGEMENT',
                'label_fa' => 'مدیریتی',
                'label_en' => 'Management',
                'description' => 'نمای مدیریتی واحدها و مراکز هزینه',
                'is_system' => false,
                'allows_user_tree' => true,
                'sort_order' => 30,
                'allowed_entity_types' => json_encode(['COMPANY', 'BUSINESS_UNIT', 'DEPARTMENT', 'COST_CENTER']),
            ],
            [
                'code' => 'TAX',
                'label_fa' => 'مالیاتی',
                'label_en' => 'Tax',
                'description' => 'گروه مالیاتی (بدون auto کامل در v1)',
                'is_system' => false,
                'allows_user_tree' => true,
                'sort_order' => 40,
                'allowed_entity_types' => json_encode(['COMPANY']),
            ],
            [
                'code' => 'CUSTOM',
                'label_fa' => 'سفارشی / گزارش',
                'label_en' => 'Custom / Reporting',
                'description' => 'درخت‌های گزارش‌دهی کاربر؛ جایگزین رشد بی‌پایان enum',
                'is_system' => false,
                'allows_user_tree' => true,
                'sort_order' => 50,
                'allowed_entity_types' => json_encode(['COMPANY', 'BRANCH', 'DEPARTMENT', 'BUSINESS_UNIT', 'COST_CENTER']),
            ],
        ];

        foreach ($rows as $row) {
            $exists = DB::table('erp_hierarchy_purpose_catalog')
                ->where('code', $row['code'])
                ->whereNull('deleted_at')
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('erp_hierarchy_purpose_catalog')->insert(array_merge($row, [
                'purpose_id' => (string) Str::uuid(),
                'is_active' => true,
                'row_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_hierarchy_purpose_catalog');
    }
};
