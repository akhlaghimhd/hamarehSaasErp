<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-P0-04 — Chart of Accounts tree (tenant-wide CoA per ownership decision)
 * parent_account_id physical FK inside Finance BC only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_acc_accounts', function (Blueprint $table) {
            $table->uuid('account_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('parent_account_id')->nullable();
            $table->string('account_code', 50);
            $table->string('name', 200);
            // 1 Asset, 2 Liability, 3 Equity, 4 Revenue, 5 Expense
            $table->smallInteger('account_type');
            $table->smallInteger('account_level')->default(1); // 1 Kol, 2 Moein, …
            // 1 Debit, 2 Credit
            $table->smallInteger('normal_balance')->default(1);
            $table->boolean('is_control_account')->default(false);
            $table->boolean('is_postable')->default(true); // leaf typically postable
            $table->smallInteger('status')->default(1);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->foreign('parent_account_id')
                ->references('account_id')
                ->on('fin_acc_accounts')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'parent_account_id'], 'idx_fin_acc_accounts_parent');
            $table->index(['tenant_id', 'account_type'], 'idx_fin_acc_accounts_type');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_fin_acc_accounts_code
            ON fin_acc_accounts (tenant_id, account_code)
            WHERE deleted_at IS NULL
        ');

        DB::statement('ALTER TABLE fin_acc_accounts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fin_acc_accounts FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_accounts');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON fin_acc_accounts
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_accounts');
        Schema::dropIfExists('fin_acc_accounts');
    }
};
