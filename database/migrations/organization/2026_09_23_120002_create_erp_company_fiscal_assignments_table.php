<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P2-02 — Company ↔ fiscal period contract
 *
 * Links Organization company to Accounting fin_fiscal_periods via logical UUID only.
 * period_id is NOT a physical FK (cross-module Law 2.2).
 * is_primary: preferred/default open period context for the company (optional).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_company_fiscal_assignments', function (Blueprint $table) {
            $table->uuid('assignment_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('company_id');
            $table->uuid('period_id'); // logical → fin_fiscal_periods.period_id
            $table->boolean('is_primary')->default(false);
            $table->smallInteger('status')->default(1);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'company_id'], 'idx_erp_co_fiscal_company');
            $table->index(['tenant_id', 'period_id'], 'idx_erp_co_fiscal_period');
        });

        // One primary assignment per company (soft-delete safe)
        DB::statement('
            CREATE UNIQUE INDEX uq_erp_co_fiscal_primary
            ON erp_company_fiscal_assignments (tenant_id, company_id)
            WHERE is_primary = true AND deleted_at IS NULL
        ');

        // Prevent duplicate company+period while active
        DB::statement('
            CREATE UNIQUE INDEX uq_erp_co_fiscal_pair
            ON erp_company_fiscal_assignments (tenant_id, company_id, period_id)
            WHERE deleted_at IS NULL
        ');

        DB::statement('ALTER TABLE erp_company_fiscal_assignments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE erp_company_fiscal_assignments FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON erp_company_fiscal_assignments');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON erp_company_fiscal_assignments
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON erp_company_fiscal_assignments');
        Schema::dropIfExists('erp_company_fiscal_assignments');
    }
};
