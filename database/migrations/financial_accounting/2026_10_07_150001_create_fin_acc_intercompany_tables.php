<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-P5 — Intercompany GL maps + paired journal links.
 * Org owns partners/rules; Finance maps to accounts and creates DRAFT pairs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_acc_ic_account_maps')) {
            Schema::create('fin_acc_ic_account_maps', function (Blueprint $table) {
                $table->uuid('ic_account_map_id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('from_company_id');
                $table->uuid('to_company_id');
                $table->uuid('due_from_account_id'); // on from_company books: receivable from to
                $table->uuid('due_to_account_id');   // on to_company books: payable to from
                $table->uuid('ic_partner_id')->nullable(); // logical → erp_intercompany_partners
                $table->boolean('is_active')->default(true);
                $table->string('description', 500)->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->uuid('created_by')->nullable();
                $table->timestampTz('updated_at')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->softDeletesTz();
                $table->uuid('deleted_by')->nullable();
                $table->bigInteger('row_version')->default(1);

                $table->index(['tenant_id', 'from_company_id', 'to_company_id'], 'idx_fin_acc_ic_map_pair');
            });

            DB::statement("
                CREATE UNIQUE INDEX uq_fin_acc_ic_map_pair
                ON fin_acc_ic_account_maps (tenant_id, from_company_id, to_company_id)
                WHERE deleted_at IS NULL
            ");

            DB::statement('ALTER TABLE fin_acc_ic_account_maps ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE fin_acc_ic_account_maps FORCE ROW LEVEL SECURITY');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_ic_account_maps');
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON fin_acc_ic_account_maps
                FOR ALL
                USING (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
            ");
        }

        if (! Schema::hasTable('fin_acc_ic_journal_pairs')) {
            Schema::create('fin_acc_ic_journal_pairs', function (Blueprint $table) {
                $table->uuid('ic_journal_pair_id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('from_company_id');
                $table->uuid('to_company_id');
                $table->uuid('from_journal_entry_id');
                $table->uuid('to_journal_entry_id');
                $table->uuid('period_id');
                $table->decimal('amount', 20, 4);
                $table->string('description', 500)->nullable();
                $table->string('status', 20)->default('DRAFT_PAIR'); // DRAFT_PAIR, PARTIAL_POSTED, POSTED
                $table->uuid('ic_partner_id')->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->uuid('created_by')->nullable();
                $table->timestampTz('updated_at')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->softDeletesTz();
                $table->uuid('deleted_by')->nullable();
                $table->bigInteger('row_version')->default(1);

                $table->index(['tenant_id', 'period_id'], 'idx_fin_acc_ic_pair_period');
            });

            DB::statement('ALTER TABLE fin_acc_ic_journal_pairs ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE fin_acc_ic_journal_pairs FORCE ROW LEVEL SECURITY');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_ic_journal_pairs');
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON fin_acc_ic_journal_pairs
                FOR ALL
                USING (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_acc_ic_journal_pairs');
        Schema::dropIfExists('fin_acc_ic_account_maps');
    }
};
