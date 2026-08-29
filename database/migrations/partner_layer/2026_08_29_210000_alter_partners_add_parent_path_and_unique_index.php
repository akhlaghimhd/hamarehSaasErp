<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * P3-D1 — Align partners table with Database Layer 3 SSOT.
 *
 * - parent_path: multi-tier partner hierarchy path
 * - Replace plain unique(tenant_id, code) with partial unique on
 *   (code, COALESCE(tenant_id, zero-uuid)) WHERE deleted_at IS NULL
 *   so platform partners (tenant_id NULL) and soft-deleted rows behave correctly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            if (!Schema::hasColumn('partners', 'parent_path')) {
                $table->text('parent_path')->nullable()->after('parent_partner_id');
            }
        });

        DB::statement('ALTER TABLE partners DROP CONSTRAINT IF EXISTS uq_partners_tenant_code');
        DB::statement('DROP INDEX IF EXISTS uq_partners_tenant_code');

        // SSOT: unique code per tenant context, including platform (NULL tenant)
        DB::statement("
            CREATE UNIQUE INDEX uq_partners_code_tenant
            ON partners (
                code,
                COALESCE(tenant_id, '00000000-0000-0000-0000-000000000000'::uuid)
            )
            WHERE deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_partners_code_tenant');

        Schema::table('partners', function (Blueprint $table) {
            $table->unique(['tenant_id', 'code'], 'uq_partners_tenant_code');
        });

        if (Schema::hasColumn('partners', 'parent_path')) {
            Schema::table('partners', function (Blueprint $table) {
                $table->dropColumn('parent_path');
            });
        }
    }
};
