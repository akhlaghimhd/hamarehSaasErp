<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG Cost Centers Model v1.0 — enrich master fields.
 * See: ORG_Cost_Centers_Model_v1.0.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('erp_cost_centers')) {
            return;
        }

        Schema::table('erp_cost_centers', function (Blueprint $table) {
            if (! Schema::hasColumn('erp_cost_centers', 'cost_center_type')) {
                $table->string('cost_center_type', 30)->default('ADMIN')->after('name');
            }
            if (! Schema::hasColumn('erp_cost_centers', 'manager_user_id')) {
                $table->uuid('manager_user_id')->nullable()->after('cost_center_type');
            }
            if (! Schema::hasColumn('erp_cost_centers', 'description')) {
                $table->string('description', 500)->nullable()->after('manager_user_id');
            }
            if (! Schema::hasColumn('erp_cost_centers', 'valid_from')) {
                $table->date('valid_from')->nullable()->after('description');
            }
            if (! Schema::hasColumn('erp_cost_centers', 'valid_to')) {
                $table->date('valid_to')->nullable()->after('valid_from');
            }
        });

        // Backfill null types if column existed without default on some envs
        DB::statement("UPDATE erp_cost_centers SET cost_center_type = 'ADMIN' WHERE cost_center_type IS NULL OR cost_center_type = ''");

        DB::statement('DROP INDEX IF EXISTS idx_erp_cc_type');
        DB::statement('CREATE INDEX idx_erp_cc_type ON erp_cost_centers (tenant_id, company_id, cost_center_type)');
    }

    public function down(): void
    {
        if (! Schema::hasTable('erp_cost_centers')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_erp_cc_type');

        Schema::table('erp_cost_centers', function (Blueprint $table) {
            foreach (['valid_to', 'valid_from', 'description', 'manager_user_id', 'cost_center_type'] as $col) {
                if (Schema::hasColumn('erp_cost_centers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
