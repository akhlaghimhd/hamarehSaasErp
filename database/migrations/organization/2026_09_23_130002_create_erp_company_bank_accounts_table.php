<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P3-01 — Company bank accounts
 * Owner: Organization. Soft delete + row_version + tenant RLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_company_bank_accounts', function (Blueprint $table) {
            $table->uuid('bank_account_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('company_id');

            $table->string('bank_name', 200);
            $table->string('account_holder_name', 200)->nullable();
            $table->string('account_number', 100);
            $table->string('iban', 50)->nullable();
            $table->string('swift_bic', 20)->nullable();
            $table->uuid('currency_id')->nullable(); // logical → currencies
            $table->string('branch_name', 200)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'company_id'], 'idx_erp_co_bank_company');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_co_bank_primary
            ON erp_company_bank_accounts (tenant_id, company_id)
            WHERE is_primary = true AND deleted_at IS NULL
        ');

        DB::statement('ALTER TABLE erp_company_bank_accounts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE erp_company_bank_accounts FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON erp_company_bank_accounts');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON erp_company_bank_accounts
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON erp_company_bank_accounts');
        Schema::dropIfExists('erp_company_bank_accounts');
    }
};
