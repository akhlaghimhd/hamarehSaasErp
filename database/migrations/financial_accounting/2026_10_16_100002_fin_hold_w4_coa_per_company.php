<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-HOLD-W4 / DEBT-FIN-CSN-001 / ADR-FIN-003 L09
 * Chart of Accounts becomes per-company (was tenant-shared).
 *
 * Backfill: existing rows → primary company of tenant (or first active company).
 * Uniqueness: (tenant_id, company_id, account_code) where not deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_acc_accounts')) {
            return;
        }

        if (! Schema::hasColumn('fin_acc_accounts', 'company_id')) {
            Schema::table('fin_acc_accounts', function (Blueprint $table) {
                $table->uuid('company_id')->nullable()->after('tenant_id');
            });
        }

        // Backfill from primary / first company per tenant
        DB::statement(<<<'SQL'
            UPDATE fin_acc_accounts a
            SET company_id = sub.company_id
            FROM (
                SELECT DISTINCT ON (c.tenant_id)
                    c.tenant_id,
                    c.company_id
                FROM erp_companies c
                WHERE c.deleted_at IS NULL
                ORDER BY c.tenant_id,
                    CASE WHEN c.is_primary IS TRUE THEN 0 ELSE 1 END,
                    c.created_at ASC NULLS LAST
            ) AS sub
            WHERE a.company_id IS NULL
              AND a.tenant_id = sub.tenant_id
        SQL);

        // Drop old tenant-only unique code index
        DB::statement('DROP INDEX IF EXISTS uq_fin_acc_accounts_code');

        // Per-company unique code
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS uq_fin_acc_accounts_company_code
            ON fin_acc_accounts (tenant_id, company_id, account_code)
            WHERE deleted_at IS NULL AND company_id IS NOT NULL
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_fin_acc_accounts_company
            ON fin_acc_accounts (tenant_id, company_id)
        ');
    }

    public function down(): void
    {
        if (! Schema::hasTable('fin_acc_accounts')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS uq_fin_acc_accounts_company_code');
        DB::statement('DROP INDEX IF EXISTS idx_fin_acc_accounts_company');

        if (Schema::hasColumn('fin_acc_accounts', 'company_id')) {
            Schema::table('fin_acc_accounts', function (Blueprint $table) {
                $table->dropColumn('company_id');
            });
        }

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS uq_fin_acc_accounts_code
            ON fin_acc_accounts (tenant_id, account_code)
            WHERE deleted_at IS NULL
        ');
    }
};
