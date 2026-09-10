<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Layer 5 MasterData compliance fix:
 * 1. Convert unique constraints/indexes to partial (WHERE deleted_at IS NULL) for SoftDeletes safety.
 * 2. Enable RLS + FORCE + tenant_isolation_policy on core tenant-scoped tables.
 *
 * Pattern taken from Organization (erp_companies) and Tenant Isolation Architecture Standard.
 *
 * Note: Original create migrations used $table->unique(..., 'uq_...'), which creates a
 * UNIQUE CONSTRAINT in PostgreSQL. Therefore we must DROP CONSTRAINT, not DROP INDEX.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- 1. business_partners: drop unique CONSTRAINT, add partial unique index ---
        DB::statement('ALTER TABLE business_partners DROP CONSTRAINT IF EXISTS uq_business_partners_tenant_code');
        DB::statement('DROP INDEX IF EXISTS uq_business_partners_tenant_code');
        DB::statement('CREATE UNIQUE INDEX uq_business_partners_tenant_code ON business_partners (tenant_id, code) WHERE deleted_at IS NULL');

        // --- 2. cost_centers: same fix ---
        DB::statement('ALTER TABLE cost_centers DROP CONSTRAINT IF EXISTS uq_cost_centers_tenant_code');
        DB::statement('DROP INDEX IF EXISTS uq_cost_centers_tenant_code');
        DB::statement('CREATE UNIQUE INDEX uq_cost_centers_tenant_code ON cost_centers (tenant_id, code) WHERE deleted_at IS NULL');

        // --- 3. RLS on business_partners ---
        DB::statement('ALTER TABLE business_partners ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE business_partners FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON business_partners');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON business_partners
            FOR ALL
            USING (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
            WITH CHECK (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
        ");

        // --- 4. RLS on cost_centers ---
        DB::statement('ALTER TABLE cost_centers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE cost_centers FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON cost_centers');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON cost_centers
            FOR ALL
            USING (
                tenant_id = nullif(current_tenant_id', true), '')::uuid
            )
            WITH CHECK (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
        ");

        // --- 5. RLS on entity_addresses ---
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

        // --- 6. RLS on entity_contact_points ---
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

        // --- 7. RLS on bank_accounts ---
        DB::statement('ALTER TABLE bank_accounts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE bank_accounts FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON bank_accounts');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON bank_accounts
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
        // Revert partial uniques to non-partial constraints (best-effort)
        DB::statement('DROP INDEX IF EXISTS uq_business_partners_tenant_code');
        DB::statement('ALTER TABLE business_partners DROP CONSTRAINT IF EXISTS uq_business_partners_tenant_code');
        DB::statement('CREATE UNIQUE INDEX uq_business_partners_tenant_code ON business_partners (tenant_id, code)');

        DB::statement('DROP INDEX IF EXISTS uq_cost_centers_tenant_code');
        DB::statement('ALTER TABLE cost_centers DROP CONSTRAINT IF EXISTS uq_cost_centers_tenant_code');
        DB::statement('CREATE UNIQUE INDEX uq_cost_centers_tenant_code ON cost_centers (tenant_id, code)');

        foreach (['business_partners', 'cost_centers', 'entity_addresses', 'entity_contact_points', 'bank_accounts'] as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
