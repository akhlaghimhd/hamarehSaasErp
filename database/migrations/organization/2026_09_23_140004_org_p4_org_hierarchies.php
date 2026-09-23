<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P4-04 / ORG-P4-05 — Multi-hierarchy (purpose-based reporting trees)
 * purposes: LEGAL, MANAGEMENT, TAX, ESTABLISHMENT, CUSTOM
 * Nodes reference entity_type + entity_id (logical; no physical FK to company/branch).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_org_hierarchies', function (Blueprint $table) {
            $table->uuid('hierarchy_id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 50);
            $table->string('name', 200);
            $table->string('purpose', 30); // LEGAL | MANAGEMENT | TAX | ESTABLISHMENT | CUSTOM
            $table->integer('version')->default(1);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'purpose'], 'idx_erp_hier_purpose');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_hier_code
            ON erp_org_hierarchies (tenant_id, code)
            WHERE deleted_at IS NULL
        ');

        Schema::create('erp_org_hierarchy_nodes', function (Blueprint $table) {
            $table->uuid('node_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('hierarchy_id');
            $table->uuid('parent_node_id')->nullable();
            $table->string('entity_type', 30); // COMPANY | BRANCH | DEPARTMENT | BUSINESS_UNIT | COST_CENTER
            $table->uuid('entity_id');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'hierarchy_id'], 'idx_erp_hier_node_hier');
            $table->index(['tenant_id', 'entity_type', 'entity_id'], 'idx_erp_hier_node_entity');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_hier_node_entity
            ON erp_org_hierarchy_nodes (tenant_id, hierarchy_id, entity_type, entity_id)
            WHERE deleted_at IS NULL
        ');

        foreach (['erp_org_hierarchies', 'erp_org_hierarchy_nodes'] as $table) {
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
        foreach (['erp_org_hierarchy_nodes', 'erp_org_hierarchies'] as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            Schema::dropIfExists($table);
        }
    }
};
