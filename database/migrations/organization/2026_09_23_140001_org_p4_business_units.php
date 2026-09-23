<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P4-01 — Business Units (management dimension; may span companies)
 * Scope type BUSINESS_UNIT prepared for later Identity wiring (P6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_business_units', function (Blueprint $table) {
            $table->uuid('business_unit_id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 50);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id'], 'idx_erp_bu_tenant');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_bu_code
            ON erp_business_units (tenant_id, code)
            WHERE deleted_at IS NULL
        ');

        Schema::create('erp_business_unit_companies', function (Blueprint $table) {
            $table->uuid('assignment_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('business_unit_id');
            $table->uuid('company_id');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'business_unit_id'], 'idx_erp_bu_co_bu');
            $table->index(['tenant_id', 'company_id'], 'idx_erp_bu_co_company');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_erp_bu_co_pair
            ON erp_business_unit_companies (tenant_id, business_unit_id, company_id)
            WHERE deleted_at IS NULL
        ');

        foreach (['erp_business_units', 'erp_business_unit_companies'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON {$table}
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
        foreach (['erp_business_unit_companies', 'erp_business_units'] as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            Schema::dropIfExists($table);
        }
    }
};
