<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P0-08 — entity_contact_points (polymorphic contact surface)
 * Aligns with 02_Master_Data_Table_Definitions.md §10
 * contact_point_type: 1=Phone, 2=Mobile, 3=Email, 4=Website
 * RLS: tenant_isolation_policy
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_contact_points', function (Blueprint $table) {
            $table->uuid('entity_contact_point_id')->primary();
            $table->uuid('tenant_id');
            $table->string('entity_type', 100);
            $table->uuid('entity_id');
            $table->smallInteger('contact_point_type'); // 1 Phone, 2 Mobile, 3 Email, 4 Website
            $table->string('contact_value', 255);
            $table->boolean('is_primary')->default(false);
            $table->smallInteger('status')->default(1);

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);
        });

        DB::statement('CREATE INDEX idx_entity_contact_polymorphic ON entity_contact_points(entity_id, entity_type) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_entity_contact_tenant ON entity_contact_points(tenant_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX uq_entity_contact_primary ON entity_contact_points(tenant_id, entity_type, entity_id, contact_point_type) WHERE is_primary = true AND deleted_at IS NULL');

        // RLS
        DB::statement('ALTER TABLE entity_contact_points ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE entity_contact_points FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON entity_contact_points');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON entity_contact_points
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON entity_contact_points');
        Schema::dropIfExists('entity_contact_points');
    }
};
