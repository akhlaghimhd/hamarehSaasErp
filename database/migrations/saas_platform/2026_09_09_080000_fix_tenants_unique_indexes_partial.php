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
 * Previous migration used non-partial unique constraints which block soft-deleted rows
 * from being re-created with the same code/slug.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop existing non-partial unique indexes/constraints
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

        // Restore original non-partial unique indexes
        DB::statement('CREATE UNIQUE INDEX uq_tenants_code ON tenants(tenant_code)');
        DB::statement('CREATE UNIQUE INDEX uq_tenants_slug ON tenants(slug)');
    }
};
