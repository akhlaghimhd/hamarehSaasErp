<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P4-03 — Align departments with company_id (backfill; keep branch_id for BC)
 * Does not drop branch_id NOT NULL — existing Scope/tests rely on branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_departments', function (Blueprint $table) {
            $table->uuid('company_id')->nullable()->after('tenant_id');
        });

        DB::statement('
            UPDATE erp_departments d
            SET company_id = b.company_id
            FROM erp_branches b
            WHERE d.branch_id = b.branch_id
              AND d.company_id IS NULL
        ');

        // Rows without resolvable branch should not exist; if any, leave null temporarily
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_erp_departments_company
            ON erp_departments (tenant_id, company_id)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_erp_departments_company');
        Schema::table('erp_departments', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });
    }
};
