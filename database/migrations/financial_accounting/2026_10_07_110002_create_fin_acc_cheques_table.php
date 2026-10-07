<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-P1-03 — Cheque register lifecycle.
 * status: RECEIVED | ISSUED | DEPOSITED | CLEARED | BOUNCED | CANCELLED
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_acc_cheques', function (Blueprint $table) {
            $table->uuid('cheque_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('company_id');
            $table->string('direction', 10); // IN | OUT
            $table->string('cheque_number', 50);
            $table->string('bank_name', 150)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('due_date');
            $table->decimal('amount', 20, 4);
            $table->uuid('currency_id')->nullable();
            $table->string('payee_name', 200)->nullable();
            $table->string('drawer_name', 200)->nullable();
            $table->string('status', 20)->default('RECEIVED');
            $table->uuid('cash_account_id')->nullable();
            $table->uuid('treasury_document_id')->nullable();
            $table->uuid('open_item_id')->nullable();
            $table->string('description', 500)->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'company_id', 'status'], 'idx_fin_acc_cheques_status');
            $table->index(['tenant_id', 'due_date'], 'idx_fin_acc_cheques_due');
        });

        DB::statement("
            ALTER TABLE fin_acc_cheques
            ADD CONSTRAINT chk_fin_acc_cheque_direction
            CHECK (direction IN ('IN', 'OUT'))
        ");
        DB::statement("
            ALTER TABLE fin_acc_cheques
            ADD CONSTRAINT chk_fin_acc_cheque_status
            CHECK (status IN ('RECEIVED', 'ISSUED', 'DEPOSITED', 'CLEARED', 'BOUNCED', 'CANCELLED'))
        ");
        DB::statement("
            ALTER TABLE fin_acc_cheques
            ADD CONSTRAINT chk_fin_acc_cheque_amount
            CHECK (amount > 0)
        ");

        DB::statement('ALTER TABLE fin_acc_cheques ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fin_acc_cheques FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_cheques');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON fin_acc_cheques
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_cheques');
        Schema::dropIfExists('fin_acc_cheques');
    }
};
