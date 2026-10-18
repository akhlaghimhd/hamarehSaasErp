<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-ID-ORG-005 L01 — membership anchor (home company) on tenant_users + backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_users')) {
            return;
        }
        if (! Schema::hasColumn('tenant_users', 'home_company_id')) {
            Schema::table('tenant_users', function (Blueprint $table) {
                $table->uuid('home_company_id')->nullable()->after('is_owner');
                $table->index(['tenant_id', 'home_company_id'], 'idx_tenant_users_home_company');
            });
        }

        // C6 — backfill null home_company_id from tenant primary (or first) company
        if (! Schema::hasTable('erp_companies')) {
            return;
        }

        $tenants = DB::table('tenant_users')
            ->whereNull('home_company_id')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenants as $tenantId) {
            $primary = DB::table('erp_companies')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->orderByRaw('CASE WHEN is_primary IS TRUE THEN 0 ELSE 1 END')
                ->orderBy('created_at')
                ->value('company_id');

            if ($primary === null) {
                continue;
            }

            DB::table('tenant_users')
                ->where('tenant_id', $tenantId)
                ->whereNull('home_company_id')
                ->update(['home_company_id' => $primary]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_users') || ! Schema::hasColumn('tenant_users', 'home_company_id')) {
            return;
        }
        Schema::table('tenant_users', function (Blueprint $table) {
            $table->dropIndex('idx_tenant_users_home_company');
            $table->dropColumn('home_company_id');
        });
    }
};
