<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ORG-P7 — Hardening: additive performance indexes (idempotent).
 * Does not change constraints or column nullability.
 */
return new class extends Migration
{
    public function up(): void
    {
        $statements = [
            // Company group spine lookups
            "CREATE INDEX IF NOT EXISTS idx_erp_companies_entity_kind ON erp_companies (tenant_id, entity_kind) WHERE deleted_at IS NULL",
            "CREATE INDEX IF NOT EXISTS idx_erp_companies_parent ON erp_companies (tenant_id, parent_company_id) WHERE deleted_at IS NULL AND parent_company_id IS NOT NULL",

            // Ownership
            "CREATE INDEX IF NOT EXISTS idx_erp_co_own_company ON erp_company_ownerships (tenant_id, company_id) WHERE deleted_at IS NULL",

            // Bank / officers
            "CREATE INDEX IF NOT EXISTS idx_erp_co_bank_active ON erp_company_bank_accounts (tenant_id, company_id, is_active) WHERE deleted_at IS NULL",
            "CREATE INDEX IF NOT EXISTS idx_erp_co_officer_active ON erp_company_officers (tenant_id, company_id, is_active) WHERE deleted_at IS NULL",

            // Cost centers / BU
            "CREATE INDEX IF NOT EXISTS idx_erp_cc_active ON erp_cost_centers (tenant_id, company_id, is_active) WHERE deleted_at IS NULL",
            "CREATE INDEX IF NOT EXISTS idx_erp_bu_active ON erp_business_units (tenant_id, is_active) WHERE deleted_at IS NULL",

            // Hierarchy nodes by parent
            "CREATE INDEX IF NOT EXISTS idx_erp_hier_node_parent ON erp_org_hierarchy_nodes (tenant_id, hierarchy_id, parent_node_id) WHERE deleted_at IS NULL",

            // IC partners
            "CREATE INDEX IF NOT EXISTS idx_erp_ic_active ON erp_intercompany_partners (tenant_id, is_active) WHERE deleted_at IS NULL",
        ];

        foreach ($statements as $sql) {
            // Skip if underlying table missing (partial deploys)
            if (preg_match('/ON (\w+)/', $sql, $m) && !Schema::hasTable($m[1])) {
                continue;
            }
            DB::statement($sql);
        }
    }

    public function down(): void
    {
        $indexes = [
            'idx_erp_companies_entity_kind',
            'idx_erp_companies_parent',
            'idx_erp_co_own_company',
            'idx_erp_co_bank_active',
            'idx_erp_co_officer_active',
            'idx_erp_cc_active',
            'idx_erp_bu_active',
            'idx_erp_hier_node_parent',
            'idx_erp_ic_active',
        ];

        foreach ($indexes as $idx) {
            DB::statement("DROP INDEX IF EXISTS {$idx}");
        }
    }
};
