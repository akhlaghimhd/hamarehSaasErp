<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L1-02 – Align tenants unique indexes with Database Layer 1 standard.
 *
 * Document requires partial unique indexes:
 *   CREATE UNIQUE INDEX uq_tenants_code ON tenants(tenant_code) WHERE deleted_at IS NULL;
 *   CREATE UNIQUE INDEX uq_tenants_slug ON tenants(slug) WHERE deleted_at IS NULL;
 *
 * Original create_tenants migration used $table->unique(...) which on PostgreSQL
 * creates a UNIQUE CONSTRAINT (not a plain index). DROP INDEX alone does not remove
 * that constraint and causes CREATE UNIQUE INDEX to fail during RefreshDatabase.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop UNIQUE CONSTRAINT form (Laravel $table->unique on pgsql)
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS uq_tenants_code');
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS uq_tenants_slug');

        // Drop INDEX form if it already exists (idempotent / re-run safe)
        DB::statement('DROP INDEX IF EXISTS uq_tenants_code');
        DB::statement('DROP INDEX IF EXISTS uq_tenants_slug');

        // Recreate as partial unique indexes (soft-delete aware)
        DB::statement('CREATE UNIQUE INDEX uq_tenants_code ON tenants(tenant_code) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX uq_tenants_slug ON tenants(slug) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_tenants_code');
        DB::statement('DROP INDEX IF EXISTS uq_tenants_slug');

        // Restore original non-partial unique constraints
        DB::statement('ALTER TABLE tenants ADD CONSTRAINT uq_tenants_code UNIQUE (tenant_code)');
        DB::statement('ALTER TABLE tenants ADD CONSTRAINT uq_tenants_slug UNIQUE (slug)');
    }
};
