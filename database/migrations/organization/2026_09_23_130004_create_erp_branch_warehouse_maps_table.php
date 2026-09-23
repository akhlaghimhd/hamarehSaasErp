<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P3-04 — Branch ↔ warehouse mapping (multi-warehouse sites)
 * warehouse_id logical → inv_warehouses; no physical FK (Law 2.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_branch_warehouse_maps', function (Blueprint $table) {
            $table->uuid('map_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->uuid('warehouse_id'); // logical
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'branch_id'], 'idx_erp_br_wh_branch');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_br_wh_pair
            ON erp_branch_warehouse_maps (tenant_id, branch_id, warehouse_id)
            WHERE deleted_at IS NULL
        ');

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_br_wh_default
            ON erp_branch_warehouse_maps (tenant_id, branch_id)
            WHERE is_default = true AND deleted_at IS NULL
        ');

        DB::statement('ALTER TABLE erp_branch_warehouse_maps ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE erp_branch_warehouse_maps FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON erp_branch_warehouse_maps');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON erp_branch_warehouse_maps
            FOR ALL
            USING (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
            WITH CHECK (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON erp_branch_warehouse_maps');
        Schema::dropIfExists('erp_branch_warehouse_maps');
    }
};
