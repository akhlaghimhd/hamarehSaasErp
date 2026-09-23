<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P3-03 / ORG-P3-04 / ORG-P3-05 — Plant-grade branch
 *
 * branch_kind: OFFICE | PLANT | WAREHOUSE_SITE | DISTRIBUTION | MIXED
 * parent_branch_id: optional multi-level tree (same tenant + same company preferred)
 * default_warehouse_id: logical UUID → inv_warehouses (no physical FK)
 * logistics flags: supports_shipping, supports_receiving, is_manufacturing_site
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_branches', function (Blueprint $table) {
            $table->string('branch_kind', 30)->default('OFFICE')->after('address');
            $table->uuid('parent_branch_id')->nullable()->after('branch_kind');
            $table->uuid('default_warehouse_id')->nullable()->after('parent_branch_id');
            $table->boolean('supports_shipping')->default(false)->after('default_warehouse_id');
            $table->boolean('supports_receiving')->default(false)->after('supports_shipping');
            $table->boolean('is_manufacturing_site')->default(false)->after('supports_receiving');
        });

        DB::statement('DROP INDEX IF EXISTS idx_erp_branches_parent');
        DB::statement('
            CREATE INDEX idx_erp_branches_parent
            ON erp_branches (tenant_id, parent_branch_id)
            WHERE deleted_at IS NULL AND parent_branch_id IS NOT NULL
        ');

        DB::statement('DROP INDEX IF EXISTS idx_erp_branches_kind');
        DB::statement('
            CREATE INDEX idx_erp_branches_kind
            ON erp_branches (tenant_id, branch_kind)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_erp_branches_parent');
        DB::statement('DROP INDEX IF EXISTS idx_erp_branches_kind');

        Schema::table('erp_branches', function (Blueprint $table) {
            $table->dropColumn([
                'branch_kind',
                'parent_branch_id',
                'default_warehouse_id',
                'supports_shipping',
                'supports_receiving',
                'is_manufacturing_site',
            ]);
        });
    }
};
