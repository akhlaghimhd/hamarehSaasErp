<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P1-01 … ORG-P1-03 — Group spine on erp_companies
 *
 * - is_primary: one active primary per tenant (partial unique)
 * - parent_company_id: same-tenant legal tree (logical UUID; no physical FK for SoftDeletes safety)
 * - entity_kind: OPERATING | CONSOLIDATION | ELIMINATION (ADR-ORG-01)
 *
 * Backfill: oldest non-deleted company per tenant becomes primary + OPERATING.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_companies', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('status');
            $table->uuid('parent_company_id')->nullable()->after('is_primary');
            $table->string('entity_kind', 20)->default('OPERATING')->after('parent_company_id');
        });

        // Backfill entity_kind for existing rows
        DB::statement("UPDATE erp_companies SET entity_kind = 'OPERATING' WHERE entity_kind IS NULL OR entity_kind = ''");

        // Mark oldest active company per tenant as primary (idempotent-ish)
        DB::statement("
            UPDATE erp_companies c
            SET is_primary = true
            FROM (
                SELECT DISTINCT ON (tenant_id) company_id
                FROM erp_companies
                WHERE deleted_at IS NULL
                ORDER BY tenant_id, created_at ASC NULLS LAST, company_id ASC
            ) firsts
            WHERE c.company_id = firsts.company_id
        ");

        DB::statement('ALTER TABLE erp_companies ALTER COLUMN entity_kind SET NOT NULL');
        DB::statement("ALTER TABLE erp_companies ALTER COLUMN entity_kind SET DEFAULT 'OPERATING'");
        DB::statement('ALTER TABLE erp_companies ALTER COLUMN is_primary SET NOT NULL');
        DB::statement('ALTER TABLE erp_companies ALTER COLUMN is_primary SET DEFAULT false');

        // One primary per tenant among non-deleted rows
        DB::statement('DROP INDEX IF EXISTS uq_erp_companies_primary');
        DB::statement('
            CREATE UNIQUE INDEX uq_erp_companies_primary
            ON erp_companies (tenant_id)
            WHERE is_primary = true AND deleted_at IS NULL
        ');

        DB::statement('DROP INDEX IF EXISTS idx_erp_companies_parent');
        DB::statement('
            CREATE INDEX idx_erp_companies_parent
            ON erp_companies (tenant_id, parent_company_id)
            WHERE deleted_at IS NULL
        ');

        DB::statement('DROP INDEX IF EXISTS idx_erp_companies_entity_kind');
        DB::statement('
            CREATE INDEX idx_erp_companies_entity_kind
            ON erp_companies (tenant_id, entity_kind)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_erp_companies_primary');
        DB::statement('DROP INDEX IF EXISTS idx_erp_companies_parent');
        DB::statement('DROP INDEX IF EXISTS idx_erp_companies_entity_kind');

        Schema::table('erp_companies', function (Blueprint $table) {
            $table->dropColumn(['is_primary', 'parent_company_id', 'entity_kind']);
        });
    }
};
