<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-P1-02 — Cash account mapping (logical bank) + treasury receipt/payment documents.
 * bank_account_id → erp_company_bank_accounts (logical). account_id → fin_acc_accounts (physical OK inside BC).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_acc_cash_accounts', function (Blueprint $table) {
            $table->uuid('cash_account_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('company_id'); // logical → erp_companies
            $table->uuid('bank_account_id')->nullable(); // logical → erp_company_bank_accounts
            $table->uuid('gl_account_id'); // → fin_acc_accounts (posting account)
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('cash_kind', 20)->default('BANK'); // BANK | PETTY_CASH
            $table->boolean('is_active')->default(true);
            $table->smallInteger('status')->default(1);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->foreign('gl_account_id', 'fk_fin_acc_cash_gl')
                ->references('account_id')
                ->on('fin_acc_accounts')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'company_id'], 'idx_fin_acc_cash_company');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_fin_acc_cash_code
            ON fin_acc_cash_accounts (tenant_id, company_id, code)
            WHERE deleted_at IS NULL
        ');

        Schema::create('fin_acc_treasury_documents', function (Blueprint $table) {
            $table->uuid('treasury_document_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('company_id');
            $table->uuid('period_id'); // logical fiscal period
            $table->uuid('cash_account_id');
            $table->string('document_type', 20); // RECEIPT | PAYMENT
            $table->string('document_number', 100)->nullable();
            $table->date('document_date');
            $table->string('status', 20)->default('DRAFT'); // DRAFT | POSTED | VOID
            $table->decimal('amount', 20, 4);
            $table->uuid('currency_id')->nullable();
            $table->string('counterparty_name', 200)->nullable();
            $table->uuid('counterparty_open_item_id')->nullable(); // logical → fin_acc_open_items after P1-04
            $table->uuid('journal_entry_id')->nullable(); // link when posted to GL
            $table->string('description', 500)->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->foreign('cash_account_id', 'fk_fin_acc_treasury_cash')
                ->references('cash_account_id')
                ->on('fin_acc_cash_accounts')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'company_id', 'document_date'], 'idx_fin_acc_treasury_co_date');
            $table->index(['tenant_id', 'status'], 'idx_fin_acc_treasury_status');
        });

        DB::statement("
            ALTER TABLE fin_acc_treasury_documents
            ADD CONSTRAINT chk_fin_acc_treasury_type
            CHECK (document_type IN ('RECEIPT', 'PAYMENT'))
        ");
        DB::statement("
            ALTER TABLE fin_acc_treasury_documents
            ADD CONSTRAINT chk_fin_acc_treasury_status
            CHECK (status IN ('DRAFT', 'POSTED', 'VOID'))
        ");
        DB::statement("
            ALTER TABLE fin_acc_treasury_documents
            ADD CONSTRAINT chk_fin_acc_treasury_amount
            CHECK (amount > 0)
        ");

        foreach (['fin_acc_cash_accounts', 'fin_acc_treasury_documents'] as $t) {
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
        foreach (['fin_acc_treasury_documents', 'fin_acc_cash_accounts'] as $t) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$t}");
        }
        Schema::dropIfExists('fin_acc_treasury_documents');
        Schema::dropIfExists('fin_acc_cash_accounts');
    }
};
