<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P0-07 — entity_addresses already created by MasterData
 * (database/migrations/master_data/2026_08_17_000005_create_entity_addresses_table.php)
 * RLS already enabled by 2026_09_10_051225_fix_partial_unique_and_enable_rls_master_data.
 *
 * This migration is intentionally idempotent: skip create if table exists,
 * ensure partial unique for primary address per entity (soft-delete safe).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('entity_addresses')) {
            // Should not happen on environments that ran MasterData migrations;
            // keep a safety create matching MasterData schema for fresh installs
            // that might order organization before master_data (unlikely).
            DB::statement("
                CREATE TABLE entity_addresses (
                    entity_address_id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                    tenant_id UUID NOT NULL,
                    entity_type VARCHAR(100) NOT NULL,
                    entity_id UUID NOT NULL,
                    address_type_id UUID NOT NULL,
                    country_id UUID NOT NULL,
                    province_id UUID NULL,
                    city_id UUID NULL,
                    postal_code VARCHAR(50) NULL,
                    address_text TEXT NOT NULL,
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
            DB::statement('CREATE INDEX idx_entity_addresses_polymorphic ON entity_addresses(entity_id, entity_type)');
            DB::statement('CREATE INDEX idx_entity_addresses_tenant ON entity_addresses(tenant_id)');
            DB::statement('ALTER TABLE entity_addresses ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE entity_addresses FORCE ROW LEVEL SECURITY');
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON entity_addresses
                FOR ALL
                USING (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
            ");
        }

        // Soft-delete-safe unique primary address per tenant+entity
        DB::statement('DROP INDEX IF EXISTS uq_entity_addresses_primary');
        DB::statement('
            CREATE UNIQUE INDEX uq_entity_addresses_primary
            ON entity_addresses (tenant_id, entity_type, entity_id)
            WHERE is_primary = true AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_entity_addresses_primary');
        // Do not drop the table — owned by MasterData module
    }
};
