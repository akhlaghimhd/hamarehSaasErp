<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy fin_acc_tax_transactions (pre-P2 / ADD sketch) has incompatible columns
 * (transaction_id, tax_type, …). Archive it and create clean P2 schema owned by Finance.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_acc_tax_transactions')) {
            // Drop RLS policy before rename
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_tax_transactions');

            if (! Schema::hasTable('fin_acc_tax_transactions_legacy')) {
                DB::statement('ALTER TABLE fin_acc_tax_transactions RENAME TO fin_acc_tax_transactions_legacy');
            } else {
                // Already archived once; drop conflicting empty-ish current name if both exist
                Schema::dropIfExists('fin_acc_tax_transactions');
            }
        }

        if (! Schema::hasTable('fin_acc_tax_transactions')) {
            Schema::create('fin_acc_tax_transactions', function (Blueprint $table) {
                $table->uuid('tax_transaction_id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('company_id');
                $table->string('source_document_type', 100);
                $table->uuid('source_document_id');
                $table->uuid('tax_rate_config_id')->nullable();
                $table->string('tax_code', 40);
                $table->decimal('taxable_amount', 20, 4);
                $table->decimal('tax_rate', 8, 4);
                $table->decimal('tax_amount', 20, 4);
                $table->date('transaction_date');
                $table->string('direction', 10)->default('OUTPUT');
                $table->uuid('journal_entry_id')->nullable();

                $table->timestampTz('created_at')->useCurrent();
                $table->uuid('created_by')->nullable();

                $table->index(
                    ['tenant_id', 'source_document_type', 'source_document_id'],
                    'idx_fin_acc_tax_txn_source'
                );
                $table->index(['tenant_id', 'company_id', 'transaction_date'], 'idx_fin_acc_tax_txn_co_date');
            });

            DB::statement("
                ALTER TABLE fin_acc_tax_transactions
                ADD CONSTRAINT chk_fin_acc_tax_direction
                CHECK (direction IN ('OUTPUT', 'INPUT'))
            ");
        }

        DB::statement('ALTER TABLE fin_acc_tax_transactions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fin_acc_tax_transactions FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_tax_transactions');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON fin_acc_tax_transactions
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
        Schema::dropIfExists('fin_acc_tax_transactions');
        if (Schema::hasTable('fin_acc_tax_transactions_legacy')) {
            DB::statement('ALTER TABLE fin_acc_tax_transactions_legacy RENAME TO fin_acc_tax_transactions');
        }
    }
};
