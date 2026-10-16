<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-HOLD-W3 / ADR-FIN-003 L07 — optional branch document series from fiscal year start.
 * - journal entries: optional branch_id (logical → erp_branches)
 * - sequences: optional branch_id with partial unique indexes
 * - company numbering settings: branch_series_enabled + effective_from_fiscal_year_key
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_acc_journal_entries') && ! Schema::hasColumn('fin_acc_journal_entries', 'branch_id')) {
            Schema::table('fin_acc_journal_entries', function (Blueprint $table) {
                $table->uuid('branch_id')->nullable()->after('company_id');
                $table->index(['tenant_id', 'company_id', 'branch_id'], 'idx_fin_acc_je_branch');
            });
        }

        if (Schema::hasTable('fin_acc_document_sequences') && ! Schema::hasColumn('fin_acc_document_sequences', 'branch_id')) {
            Schema::table('fin_acc_document_sequences', function (Blueprint $table) {
                $table->uuid('branch_id')->nullable()->after('company_id');
            });

            DB::statement('DROP INDEX IF EXISTS uq_fin_acc_document_sequences');
            DB::statement('
                CREATE UNIQUE INDEX uq_fin_acc_document_sequences_company
                ON fin_acc_document_sequences (tenant_id, company_id, sequence_key, fiscal_year_key)
                WHERE branch_id IS NULL
            ');
            DB::statement('
                CREATE UNIQUE INDEX uq_fin_acc_document_sequences_branch
                ON fin_acc_document_sequences (tenant_id, company_id, branch_id, sequence_key, fiscal_year_key)
                WHERE branch_id IS NOT NULL
            ');
        }

        if (! Schema::hasTable('fin_acc_company_numbering_settings')) {
            Schema::create('fin_acc_company_numbering_settings', function (Blueprint $table) {
                $table->uuid('setting_id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('company_id');
                $table->boolean('branch_series_enabled')->default(false);
                $table->string('branch_series_from_fiscal_year', 20)->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->bigInteger('row_version')->default(1);

                $table->unique(['tenant_id', 'company_id'], 'uq_fin_acc_numbering_company');
            });

            DB::statement('ALTER TABLE fin_acc_company_numbering_settings ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE fin_acc_company_numbering_settings FORCE ROW LEVEL SECURITY');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_company_numbering_settings');
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON fin_acc_company_numbering_settings
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
        if (Schema::hasTable('fin_acc_company_numbering_settings')) {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_company_numbering_settings');
            Schema::dropIfExists('fin_acc_company_numbering_settings');
        }

        if (Schema::hasTable('fin_acc_document_sequences') && Schema::hasColumn('fin_acc_document_sequences', 'branch_id')) {
            DB::statement('DROP INDEX IF EXISTS uq_fin_acc_document_sequences_company');
            DB::statement('DROP INDEX IF EXISTS uq_fin_acc_document_sequences_branch');
            Schema::table('fin_acc_document_sequences', function (Blueprint $table) {
                $table->dropColumn('branch_id');
            });
            DB::statement('
                CREATE UNIQUE INDEX uq_fin_acc_document_sequences
                ON fin_acc_document_sequences (tenant_id, company_id, sequence_key, fiscal_year_key)
            ');
        }

        if (Schema::hasTable('fin_acc_journal_entries') && Schema::hasColumn('fin_acc_journal_entries', 'branch_id')) {
            Schema::table('fin_acc_journal_entries', function (Blueprint $table) {
                $table->dropIndex('idx_fin_acc_je_branch');
                $table->dropColumn('branch_id');
            });
        }
    }
};
