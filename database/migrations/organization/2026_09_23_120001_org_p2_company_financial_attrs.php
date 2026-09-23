<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P2-01 / ORG-P2-03 — Financial attributes on legal entity
 *
 * base_currency_id      → logical UUID to MasterData currencies.currency_id (platform)
 * chart_of_accounts_id  → logical UUID to Accounting CoA set / root (future or fin_accounts root)
 * No physical FK across module boundaries (Law 2.2 / 2.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_companies', function (Blueprint $table) {
            $table->uuid('base_currency_id')->nullable()->after('entity_kind');
            $table->uuid('chart_of_accounts_id')->nullable()->after('base_currency_id');
            $table->string('default_consol_rate_type', 20)->nullable()->after('chart_of_accounts_id');
        });

        DB::statement('DROP INDEX IF EXISTS idx_erp_companies_base_currency');
        DB::statement('
            CREATE INDEX idx_erp_companies_base_currency
            ON erp_companies (tenant_id, base_currency_id)
            WHERE deleted_at IS NULL AND base_currency_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_erp_companies_base_currency');

        Schema::table('erp_companies', function (Blueprint $table) {
            $table->dropColumn(['base_currency_id', 'chart_of_accounts_id', 'default_consol_rate_type']);
        });
    }
};
