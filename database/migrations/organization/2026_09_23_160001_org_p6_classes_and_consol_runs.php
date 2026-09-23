<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P6-01 / ORG-P6-05
 * Parallel org classification dimension + consolidation run placeholder (Org side).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_org_classes', function (Blueprint $table) {
            $table->uuid('org_class_id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 50);
            $table->string('name', 200);
            $table->string('dimension', 50)->default('LOCATION'); // LOCATION | CUSTOM | …
            $table->uuid('parent_org_class_id')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'dimension'], 'idx_erp_org_class_dim');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_org_class_code
            ON erp_org_classes (tenant_id, code)
            WHERE deleted_at IS NULL
        ');

        Schema::create('erp_consolidation_runs', function (Blueprint $table) {
            $table->uuid('consol_run_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('hierarchy_id')->nullable(); // snapshot source hierarchy
            $table->string('code', 50);
            $table->string('name', 200);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->uuid('rate_set_ref')->nullable(); // logical FX rate set
            $table->string('status', 30)->default('DRAFT'); // DRAFT | SNAPSHOTTED | POSTED | CANCELLED
            $table->jsonb('snapshot_payload')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'status'], 'idx_erp_consol_status');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_consol_run_code
            ON erp_consolidation_runs (tenant_id, code)
            WHERE deleted_at IS NULL
        ');

        foreach (['erp_org_classes', 'erp_consolidation_runs'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON {$table}
                FOR ALL
                USING (
                    tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
                )
                WITH CHECK (
                    tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
                )
            ");
        }
    }

    public function down(): void
    {
        foreach (['erp_consolidation_runs', 'erp_org_classes'] as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            Schema::dropIfExists($table);
        }
    }
};
