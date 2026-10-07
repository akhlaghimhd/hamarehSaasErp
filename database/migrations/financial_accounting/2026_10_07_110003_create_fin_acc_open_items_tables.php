<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-P1-04 — AR/AP open items + partial allocations.
 * side: AR (receivable) | AP (payable)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_acc_open_items', function (Blueprint $table) {
            $table->uuid('open_item_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('company_id');
            $table->string('side', 5); // AR | AP
            $table->string('document_type', 40)->default('MANUAL_INVOICE');
            $table->string('document_number', 100)->nullable();
            $table->date('document_date');
            $table->date('due_date')->nullable();
            $table->string('counterparty_name', 200);
            $table->uuid('counterparty_ref_id')->nullable(); // future customer/vendor logical
            $table->decimal('original_amount', 20, 4);
            $table->decimal('open_amount', 20, 4);
            $table->uuid('currency_id')->nullable();
            $table->uuid('gl_account_id')->nullable(); // control account
            $table->uuid('journal_entry_id')->nullable();
            $table->string('status', 20)->default('OPEN'); // OPEN | PARTIAL | CLOSED | VOID
            $table->string('description', 500)->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'company_id', 'side', 'status'], 'idx_fin_acc_oi_side_status');
            $table->index(['tenant_id', 'due_date'], 'idx_fin_acc_oi_due');
        });

        DB::statement("
            ALTER TABLE fin_acc_open_items
            ADD CONSTRAINT chk_fin_acc_oi_side CHECK (side IN ('AR', 'AP'))
        ");
        DB::statement("
            ALTER TABLE fin_acc_open_items
            ADD CONSTRAINT chk_fin_acc_oi_status
            CHECK (status IN ('OPEN', 'PARTIAL', 'CLOSED', 'VOID'))
        ");
        DB::statement("
            ALTER TABLE fin_acc_open_items
            ADD CONSTRAINT chk_fin_acc_oi_amounts
            CHECK (original_amount > 0 AND open_amount >= 0 AND open_amount <= original_amount)
        ");

        Schema::create('fin_acc_open_item_allocations', function (Blueprint $table) {
            $table->uuid('allocation_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('open_item_id');
            $table->uuid('treasury_document_id')->nullable();
            $table->uuid('journal_entry_id')->nullable();
            $table->decimal('allocated_amount', 20, 4);
            $table->date('allocation_date');
            $table->string('description', 500)->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();

            $table->foreign('open_item_id', 'fk_fin_acc_alloc_oi')
                ->references('open_item_id')
                ->on('fin_acc_open_items')
                ->onDelete('restrict');

            $table->index(['open_item_id'], 'idx_fin_acc_alloc_oi');
        });

        DB::statement("
            ALTER TABLE fin_acc_open_item_allocations
            ADD CONSTRAINT chk_fin_acc_alloc_amount CHECK (allocated_amount > 0)
        ");

        foreach (['fin_acc_open_items', 'fin_acc_open_item_allocations'] as $t) {
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
        foreach (['fin_acc_open_item_allocations', 'fin_acc_open_items'] as $t) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$t}");
        }
        Schema::dropIfExists('fin_acc_open_item_allocations');
        Schema::dropIfExists('fin_acc_open_items');
    }
};
