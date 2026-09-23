<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P0-08 — entity_contact_points already created by MasterData
 * (database/migrations/master_data/2026_08_17_000006_create_entity_contact_points_table.php)
 * RLS already enabled by 2026_09_10_051225.
 *
 * Idempotent: skip create if exists; ensure partial unique for primary per type.
 * Note: PK is contact_point_id; contact_type is string (EMAIL/PHONE/MOBILE/FAX).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('entity_contact_points')) {
            DB::statement("
                CREATE TABLE entity_contact_points (
                    contact_point_id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                    tenant_id UUID NOT NULL,
                    entity_type VARCHAR(100) NOT NULL,
                    entity_id UUID NOT NULL,
                    contact_type VARCHAR(50) NOT NULL,
                    contact_value VARCHAR(255) NOT NULL,
                    extension VARCHAR(20) NULL,
                    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
                    status SMALLINT NOT NULL DEFAULT 1,
                    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                    created_by UUID NULL,
                    updated_at TIMESTAMPTZ NULL,
                    updated_by UUID NULL,
                    deleted_at TIMESTAMPTZ NULL,
                    deleted_by UUID NULL,
                    row_version BIGINT NOT NULL DEFAULT 1
                )
            ");
            DB::statement('CREATE INDEX idx_contact_points_polymorphic ON entity_contact_points(entity_id, entity_type)');
            DB::statement('CREATE INDEX idx_contact_points_tenant ON entity_contact_points(tenant_id)');
            DB::statement('ALTER TABLE entity_contact_points ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE entity_contact_points FORCE ROW LEVEL SECURITY');
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON entity_contact_points
                FOR ALL
                USING (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
            ");
        }

        DB::statement('DROP INDEX IF EXISTS uq_entity_contact_primary');
        DB::statement('
            CREATE UNIQUE INDEX uq_entity_contact_primary
            ON entity_contact_points (tenant_id, entity_type, entity_id, contact_type)
            WHERE is_primary = true AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_entity_contact_primary');
        // Do not drop the table — owned by MasterData module
    }
};
