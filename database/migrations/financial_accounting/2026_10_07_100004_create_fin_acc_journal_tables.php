<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-P0-06 — Journal entries + items (double-entry)
 * FK only inside Finance BC. company_id / period_id / currency / dimensions = logical UUID.
 * Posted docs: reverse only (soft-delete of header allowed only for DRAFT).
 *
 * Self-referential reverses_entry_id FK added after table create (PostgreSQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_acc_journal_entries', function (Blueprint $table) {
            $table->uuid('journal_entry_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('ledger_id');
            $table->uuid('company_id'); // logical → erp_companies
            $table->uuid('period_id'); // logical → fin_fiscal_periods
            $table->string('entry_number', 100)->nullable(); // assigned on post
            $table->date('document_date');
            $table->timestampTz('posting_date')->nullable();
            $table->string('status', 20)->default('DRAFT'); // DRAFT, POSTED, REVERSED
            $table->string('source_document_type', 100)->nullable();
            $table->uuid('source_document_id')->nullable();
            $table->uuid('reverses_entry_id')->nullable(); // link to original if this is reverse
            $table->uuid('reversed_by_entry_id')->nullable(); // original points to reverse
            $table->string('description', 500)->nullable();
            $table->uuid('posted_by')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->foreign('ledger_id')
                ->references('ledger_id')
                ->on('fin_acc_ledgers')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'company_id', 'period_id'], 'idx_fin_acc_je_company_period');
            $table->index(['tenant_id', 'status'], 'idx_fin_acc_je_status');
            $table->index(['tenant_id', 'source_document_type', 'source_document_id'], 'idx_fin_acc_je_source');
        });

        Schema::table('fin_acc_journal_entries', function (Blueprint $table) {
            $table->foreign('reverses_entry_id', 'fk_fin_acc_je_reverses')
                ->references('journal_entry_id')
                ->on('fin_acc_journal_entries')
                ->onDelete('restrict');
        });

        DB::statement("
            ALTER TABLE fin_acc_journal_entries
            ADD CONSTRAINT chk_fin_acc_je_status
            CHECK (status IN ('DRAFT', 'POSTED', 'REVERSED'))
        ");

        // entry_number unique per company when set (soft-delete safe)
        DB::statement('
            CREATE UNIQUE INDEX uq_fin_acc_je_entry_number
            ON fin_acc_journal_entries (tenant_id, company_id, entry_number)
            WHERE entry_number IS NOT NULL AND deleted_at IS NULL
        ');

        Schema::create('fin_acc_journal_items', function (Blueprint $table) {
            $table->uuid('journal_item_id')->primary();
            $table->uuid('journal_entry_id');
            $table->uuid('tenant_id');
            $table->uuid('account_id');
            $table->uuid('cost_center_id')->nullable(); // logical → erp_cost_centers
            $table->uuid('business_unit_id')->nullable(); // logical → erp_business_units
            $table->decimal('debit_amount', 20, 4)->default(0);
            $table->decimal('credit_amount', 20, 4)->default(0);
            $table->uuid('currency_id')->nullable(); // logical
            $table->decimal('exchange_rate', 20, 8)->default(1);
            $table->decimal('source_currency_amount', 20, 4)->default(0);
            $table->string('description', 500)->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();

            $table->foreign('journal_entry_id')
                ->references('journal_entry_id')
                ->on('fin_acc_journal_entries')
                ->onDelete('restrict');

            $table->foreign('account_id')
                ->references('account_id')
                ->on('fin_acc_accounts')
                ->onDelete('restrict');

            $table->index(['journal_entry_id'], 'idx_fin_acc_ji_entry');
            $table->index(['tenant_id', 'account_id'], 'idx_fin_acc_ji_account');
        });

        // Debit XOR Credit (exactly one side positive); zero/zero allowed for draft scaffolding
        DB::statement('
            ALTER TABLE fin_acc_journal_items
            ADD CONSTRAINT chk_fin_acc_ji_amounts
            CHECK (
                (debit_amount > 0 AND credit_amount = 0)
                OR (credit_amount > 0 AND debit_amount = 0)
                OR (debit_amount = 0 AND credit_amount = 0)
            )
        ');

        DB::statement('ALTER TABLE fin_acc_journal_entries ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fin_acc_journal_entries FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_journal_entries');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON fin_acc_journal_entries
            FOR ALL
            USING (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
            WITH CHECK (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
        ");

        DB::statement('ALTER TABLE fin_acc_journal_items ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fin_acc_journal_items FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_journal_items');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON fin_acc_journal_items
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_journal_items');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_journal_entries');
        Schema::dropIfExists('fin_acc_journal_items');
        Schema::dropIfExists('fin_acc_journal_entries');
    }
};
