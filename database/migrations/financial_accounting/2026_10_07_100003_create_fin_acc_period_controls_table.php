<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-P0-05 — Posting control state per company + fiscal period
 * period_id logical → fin_fiscal_periods (calendar SoT; not duplicated here).
 * company_id logical → erp_companies.
 * control_status: OPEN | SOFT_CLOSED | HARD_CLOSED
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_acc_period_controls', function (Blueprint $table) {
            $table->uuid('period_control_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('company_id');
            $table->uuid('period_id'); // logical → fin_fiscal_periods.period_id
            $table->string('control_status', 20)->default('OPEN'); // OPEN, SOFT_CLOSED, HARD_CLOSED
            $table->timestampTz('soft_closed_at')->nullable();
            $table->uuid('soft_closed_by')->nullable();
            $table->timestampTz('hard_closed_at')->nullable();
            $table->uuid('hard_closed_by')->nullable();
            $table->text('notes')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'company_id'], 'idx_fin_acc_pc_company');
            $table->index(['tenant_id', 'period_id'], 'idx_fin_acc_pc_period');
            $table->index(['tenant_id', 'control_status'], 'idx_fin_acc_pc_status');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_fin_acc_period_controls_pair
            ON fin_acc_period_controls (tenant_id, company_id, period_id)
            WHERE deleted_at IS NULL
        ');

        DB::statement("
            ALTER TABLE fin_acc_period_controls
            ADD CONSTRAINT chk_fin_acc_pc_status
            CHECK (control_status IN ('OPEN', 'SOFT_CLOSED', 'HARD_CLOSED'))
        ");

        DB::statement('ALTER TABLE fin_acc_period_controls ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fin_acc_period_controls FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_period_controls');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON fin_acc_period_controls
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_period_controls');
        Schema::dropIfExists('fin_acc_period_controls');
    }
};
