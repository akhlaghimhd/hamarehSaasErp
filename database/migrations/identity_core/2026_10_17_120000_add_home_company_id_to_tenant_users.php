<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-ID-ORG-005 L01 — membership anchor (home company) on tenant_users.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_users')) {
            return;
        }
        if (Schema::hasColumn('tenant_users', 'home_company_id')) {
            return;
        }

        Schema::table('tenant_users', function (Blueprint $table) {
            $table->uuid('home_company_id')->nullable()->after('is_owner');
            $table->index(['tenant_id', 'home_company_id'], 'idx_tenant_users_home_company');
        });
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
