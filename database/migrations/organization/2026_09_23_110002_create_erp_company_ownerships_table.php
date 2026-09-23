<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P1-04 — Company ownership percentages / relation types
 *
 * company_id: subsidiary (owned entity)
 * owner_company_id: parent / shareholder legal entity (logical UUID, same tenant expected)
 * No physical FK (SoftDeletes + Law 2.2/2.3 style logical refs even within org tree edge cases)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_company_ownerships', function (Blueprint $table) {
            $table->uuid('ownership_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('company_id');
            $table->uuid('owner_company_id');
            $table->decimal('ownership_percent', 7, 4); // e.g. 100.0000
            $table->string('relation_type', 30)->default('EQUITY'); // EQUITY, CONTROL, OTHER
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->smallInteger('status')->default(1);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'company_id'], 'idx_erp_company_ownerships_company');
            $table->index(['tenant_id', 'owner_company_id'], 'idx_erp_company_ownerships_owner');
        });

        DB::statement('ALTER TABLE erp_company_ownerships ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE erp_company_ownerships FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON erp_company_ownerships');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON erp_company_ownerships
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON erp_company_ownerships');
        Schema::dropIfExists('erp_company_ownerships');
    }
};
