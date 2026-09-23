<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P3-02 — Company officers (role + person/user logical refs + mandate)
 * person_user_id → Identity users (logical UUID, no physical FK)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_company_officers', function (Blueprint $table) {
            $table->uuid('officer_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('company_id');

            $table->string('role_code', 50); // CEO, CFO, BOARD_MEMBER, SIGNATORY, …
            $table->string('role_title', 200)->nullable();
            $table->string('full_name', 200);
            $table->uuid('person_user_id')->nullable(); // logical → users
            $table->string('national_id', 50)->nullable();
            $table->date('mandate_from')->nullable();
            $table->date('mandate_to')->nullable();
            $table->boolean('has_signing_authority')->default(false);
            $table->text('mandate_notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'company_id'], 'idx_erp_co_officer_company');
            $table->index(['tenant_id', 'role_code'], 'idx_erp_co_officer_role');
        });

        DB::statement('ALTER TABLE erp_company_officers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE erp_company_officers FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON erp_company_officers');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON erp_company_officers
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON erp_company_officers');
        Schema::dropIfExists('erp_company_officers');
    }
};
