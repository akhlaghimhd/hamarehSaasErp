<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-P0-03 — Leading ledger per company (Universal Journal style)
 * company_id is logical UUID → erp_companies (Law 2.2, no physical FK).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_acc_ledgers', function (Blueprint $table) {
            $table->uuid('ledger_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('company_id'); // logical → erp_companies
            $table->string('code', 30);
            $table->string('name', 150);
            $table->boolean('is_leading')->default(true);
            $table->uuid('base_currency_id')->nullable(); // logical → platform currency
            $table->smallInteger('status')->default(1);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'company_id'], 'idx_fin_acc_ledgers_company');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_fin_acc_ledgers_code
            ON fin_acc_ledgers (tenant_id, company_id, code)
            WHERE deleted_at IS NULL
        ');

        // One leading ledger per company (soft-delete safe)
        DB::statement('
            CREATE UNIQUE INDEX uq_fin_acc_ledgers_leading
            ON fin_acc_ledgers (tenant_id, company_id)
            WHERE is_leading = true AND deleted_at IS NULL
        ');

        DB::statement('ALTER TABLE fin_acc_ledgers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fin_acc_ledgers FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_ledgers');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON fin_acc_ledgers
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_acc_ledgers');
        Schema::dropIfExists('fin_acc_ledgers');
    }
};
