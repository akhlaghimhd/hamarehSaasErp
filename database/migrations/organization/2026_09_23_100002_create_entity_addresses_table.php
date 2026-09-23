<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P0-07 — entity_addresses (polymorphic: COMPANY, BRANCH, BUSINESS_PARTNER, …)
 * Aligns with 02_Master_Data_Table_Definitions.md §9
 * RLS: tenant_isolation_policy (same pattern as erp_companies)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_addresses', function (Blueprint $table) {
            $table->uuid('entity_address_id')->primary();
            $table->uuid('tenant_id');
            $table->string('entity_type', 100); // COMPANY | BRANCH | BUSINESS_PARTNER | …
            $table->uuid('entity_id');
            // Logical refs to platform lookup (nullable until lookup tables land)
            $table->uuid('address_type_id')->nullable();
            $table->uuid('country_id')->nullable();
            $table->string('province_name', 150)->nullable();
            $table->string('city_name', 150)->nullable();
            $table->string('postal_code', 50)->nullable();
            $table->text('address_text');
            $table->boolean('is_primary')->default(false);
            $table->smallInteger('status')->default(1); // 1=Active

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);
        });

        DB::statement('CREATE INDEX idx_entity_addresses_polymorphic ON entity_addresses(entity_id, entity_type) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_entity_addresses_tenant ON entity_addresses(tenant_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX uq_entity_addresses_primary ON entity_addresses(tenant_id, entity_type, entity_id) WHERE is_primary = true AND deleted_at IS NULL');

        // RLS
        DB::statement('ALTER TABLE entity_addresses ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE entity_addresses FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON entity_addresses');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON entity_addresses
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON entity_addresses');
        Schema::dropIfExists('entity_addresses');
    }
};
