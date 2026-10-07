<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-P4 — Fixed assets + depreciation + account dimension flags + period close checklist.
 * cost_center / BU remain Org SoT (logical UUID only).
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Account dimension requirements (G) ---
        if (Schema::hasTable('fin_acc_accounts')) {
            Schema::table('fin_acc_accounts', function (Blueprint $table) {
                if (! Schema::hasColumn('fin_acc_accounts', 'requires_cost_center')) {
                    $table->boolean('requires_cost_center')->default(false);
                }
                if (! Schema::hasColumn('fin_acc_accounts', 'requires_business_unit')) {
                    $table->boolean('requires_business_unit')->default(false);
                }
            });
        }

        // --- Fixed assets (F) ---
        if (! Schema::hasTable('fin_acc_fixed_assets')) {
            Schema::create('fin_acc_fixed_assets', function (Blueprint $table) {
                $table->uuid('fixed_asset_id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('company_id');
                $table->string('asset_code', 40);
                $table->string('name', 200);
                $table->uuid('asset_account_id'); // GL asset account
                $table->uuid('accum_depr_account_id'); // accumulated depreciation
                $table->uuid('depr_expense_account_id'); // expense
                $table->uuid('cost_center_id')->nullable(); // logical Org
                $table->date('acquisition_date');
                $table->decimal('acquisition_cost', 20, 4);
                $table->decimal('salvage_value', 20, 4)->default(0);
                $table->unsignedInteger('useful_life_months');
                $table->string('depreciation_method', 20)->default('STRAIGHT_LINE');
                $table->decimal('book_value', 20, 4);
                $table->decimal('accumulated_depreciation', 20, 4)->default(0);
                $table->string('status', 20)->default('ACTIVE'); // ACTIVE, DISPOSED, FULLY_DEPRECIATED
                $table->date('last_depreciated_through')->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->uuid('created_by')->nullable();
                $table->timestampTz('updated_at')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->softDeletesTz();
                $table->uuid('deleted_by')->nullable();
                $table->bigInteger('row_version')->default(1);

                $table->index(['tenant_id', 'company_id'], 'idx_fin_acc_fa_company');
                $table->index(['tenant_id', 'status'], 'idx_fin_acc_fa_status');
            });

            DB::statement("
                CREATE UNIQUE INDEX uq_fin_acc_fa_code
                ON fin_acc_fixed_assets (tenant_id, company_id, asset_code)
                WHERE deleted_at IS NULL
            ");

            DB::statement("
                ALTER TABLE fin_acc_fixed_assets
                ADD CONSTRAINT chk_fin_acc_fa_status
                CHECK (status IN ('ACTIVE', 'DISPOSED', 'FULLY_DEPRECIATED'))
            ");

            DB::statement('ALTER TABLE fin_acc_fixed_assets ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE fin_acc_fixed_assets FORCE ROW LEVEL SECURITY');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_fixed_assets');
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON fin_acc_fixed_assets
                FOR ALL
                USING (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
            ");
        }

        if (! Schema::hasTable('fin_acc_depreciation_runs')) {
            Schema::create('fin_acc_depreciation_runs', function (Blueprint $table) {
                $table->uuid('depreciation_run_id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('company_id');
                $table->uuid('period_id');
                $table->uuid('ledger_id');
                $table->date('run_date');
                $table->string('status', 20)->default('DRAFT'); // DRAFT, POSTED_AS_JOURNAL, CANCELLED
                $table->uuid('journal_entry_id')->nullable();
                $table->decimal('total_amount', 20, 4)->default(0);
                $table->unsignedInteger('asset_count')->default(0);
                $table->timestampTz('created_at')->useCurrent();
                $table->uuid('created_by')->nullable();
                $table->timestampTz('updated_at')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->softDeletesTz();
                $table->uuid('deleted_by')->nullable();
                $table->bigInteger('row_version')->default(1);

                $table->index(['tenant_id', 'company_id', 'period_id'], 'idx_fin_acc_depr_period');
            });

            DB::statement('ALTER TABLE fin_acc_depreciation_runs ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE fin_acc_depreciation_runs FORCE ROW LEVEL SECURITY');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_depreciation_runs');
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON fin_acc_depreciation_runs
                FOR ALL
                USING (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
            ");
        }

        if (! Schema::hasTable('fin_acc_depreciation_run_lines')) {
            Schema::create('fin_acc_depreciation_run_lines', function (Blueprint $table) {
                $table->uuid('depreciation_line_id')->primary();
                $table->uuid('depreciation_run_id');
                $table->uuid('tenant_id');
                $table->uuid('fixed_asset_id');
                $table->decimal('amount', 20, 4);
                $table->decimal('book_value_after', 20, 4);
                $table->timestampTz('created_at')->useCurrent();

                $table->foreign('depreciation_run_id')
                    ->references('depreciation_run_id')
                    ->on('fin_acc_depreciation_runs')
                    ->onDelete('restrict');

                $table->index(['depreciation_run_id'], 'idx_fin_acc_depr_line_run');
            });

            DB::statement('ALTER TABLE fin_acc_depreciation_run_lines ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE fin_acc_depreciation_run_lines FORCE ROW LEVEL SECURITY');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_depreciation_run_lines');
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON fin_acc_depreciation_run_lines
                FOR ALL
                USING (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
            ");
        }

        // --- Period close checklist snapshot (K4) ---
        if (! Schema::hasTable('fin_acc_period_close_checklists')) {
            Schema::create('fin_acc_period_close_checklists', function (Blueprint $table) {
                $table->uuid('checklist_id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('company_id');
                $table->uuid('period_id');
                $table->jsonb('items_json'); // list of {code, severity, title, message, blocking}
                $table->boolean('has_blocking')->default(false);
                $table->timestampTz('evaluated_at')->useCurrent();
                $table->uuid('evaluated_by')->nullable();
                $table->timestampTz('created_at')->useCurrent();

                $table->index(['tenant_id', 'company_id', 'period_id'], 'idx_fin_acc_pcl_period');
            });

            DB::statement('ALTER TABLE fin_acc_period_close_checklists ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE fin_acc_period_close_checklists FORCE ROW LEVEL SECURITY');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_period_close_checklists');
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON fin_acc_period_close_checklists
                FOR ALL
                USING (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_acc_period_close_checklists');
        Schema::dropIfExists('fin_acc_depreciation_run_lines');
        Schema::dropIfExists('fin_acc_depreciation_runs');
        Schema::dropIfExists('fin_acc_fixed_assets');

        if (Schema::hasTable('fin_acc_accounts')) {
            Schema::table('fin_acc_accounts', function (Blueprint $table) {
                if (Schema::hasColumn('fin_acc_accounts', 'requires_business_unit')) {
                    $table->dropColumn('requires_business_unit');
                }
                if (Schema::hasColumn('fin_acc_accounts', 'requires_cost_center')) {
                    $table->dropColumn('requires_cost_center');
                }
            });
        }
    }
};
