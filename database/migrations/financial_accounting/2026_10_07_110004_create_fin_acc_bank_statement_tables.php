<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-P1-05 — Bank statement header + lines for reconciliation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_acc_bank_statements', function (Blueprint $table) {
            $table->uuid('bank_statement_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('company_id');
            $table->uuid('cash_account_id');
            $table->date('statement_date');
            $table->string('reference', 100)->nullable();
            $table->decimal('opening_balance', 20, 4)->default(0);
            $table->decimal('closing_balance', 20, 4)->default(0);
            $table->string('status', 20)->default('OPEN'); // OPEN | RECONCILED

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->foreign('cash_account_id', 'fk_fin_acc_stmt_cash')
                ->references('cash_account_id')
                ->on('fin_acc_cash_accounts')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'company_id'], 'idx_fin_acc_stmt_company');
        });

        DB::statement("
            ALTER TABLE fin_acc_bank_statements
            ADD CONSTRAINT chk_fin_acc_stmt_status
            CHECK (status IN ('OPEN', 'RECONCILED'))
        ");

        Schema::create('fin_acc_bank_statement_lines', function (Blueprint $table) {
            $table->uuid('bank_statement_line_id')->primary();
            $table->uuid('bank_statement_id');
            $table->uuid('tenant_id');
            $table->date('line_date');
            $table->string('description', 500)->nullable();
            $table->decimal('debit_amount', 20, 4)->default(0);
            $table->decimal('credit_amount', 20, 4)->default(0);
            $table->string('status', 20)->default('OPEN'); // OPEN | MATCHED
            $table->uuid('matched_treasury_document_id')->nullable();
            $table->uuid('matched_journal_entry_id')->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('bank_statement_id', 'fk_fin_acc_stmt_line_hdr')
                ->references('bank_statement_id')
                ->on('fin_acc_bank_statements')
                ->onDelete('restrict');

            $table->index(['bank_statement_id'], 'idx_fin_acc_stmt_lines');
        });

        DB::statement("
            ALTER TABLE fin_acc_bank_statement_lines
            ADD CONSTRAINT chk_fin_acc_stmt_line_status
            CHECK (status IN ('OPEN', 'MATCHED'))
        ");

        foreach (['fin_acc_bank_statements', 'fin_acc_bank_statement_lines'] as $t) {
            DB::statement("ALTER TABLE {$t} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$t} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$t}");
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON {$t}
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
        foreach (['fin_acc_bank_statement_lines', 'fin_acc_bank_statements'] as $t) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$t}");
        }
        Schema::dropIfExists('fin_acc_bank_statement_lines');
        Schema::dropIfExists('fin_acc_bank_statements');
    }
};
