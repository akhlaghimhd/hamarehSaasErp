<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P5-04 / ORG-P5-05 — Intercompany partner mapping + rules
 * Logical refs only: no physical FK to MasterData BP / Accounting.
 * partner_customer_id / partner_vendor_id are optional logical UUIDs to BP module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_intercompany_partners', function (Blueprint $table) {
            $table->uuid('ic_partner_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('from_company_id');
            $table->uuid('to_company_id');
            $table->uuid('partner_customer_id')->nullable(); // logical BP customer
            $table->uuid('partner_vendor_id')->nullable();   // logical BP vendor
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'from_company_id'], 'idx_erp_ic_from');
            $table->index(['tenant_id', 'to_company_id'], 'idx_erp_ic_to');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_ic_pair
            ON erp_intercompany_partners (tenant_id, from_company_id, to_company_id)
            WHERE deleted_at IS NULL
        ');

        Schema::create('erp_intercompany_rules', function (Blueprint $table) {
            $table->uuid('ic_rule_id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 50);
            $table->string('name', 200);
            $table->string('source_doc_type', 50); // e.g. SALES_INVOICE, PO, STOCK_TRANSFER
            $table->string('target_doc_type', 50);
            $table->boolean('auto_create_mirror')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'source_doc_type'], 'idx_erp_ic_rule_src');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_ic_rule_code
            ON erp_intercompany_rules (tenant_id, code)
            WHERE deleted_at IS NULL
        ');

        foreach (['erp_intercompany_partners', 'erp_intercompany_rules'] as $table) {
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
        foreach (['erp_intercompany_rules', 'erp_intercompany_partners'] as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            Schema::dropIfExists($table);
        }
    }
};
