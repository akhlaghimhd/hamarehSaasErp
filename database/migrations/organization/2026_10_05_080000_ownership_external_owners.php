<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allow shareholders outside platform (natural/legal persons).
 * owner_company_id becomes optional when owner_kind is EXTERNAL_*.
 * Does NOT create users/companies/branches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_company_ownerships', function (Blueprint $table) {
            if (! Schema::hasColumn('erp_company_ownerships', 'owner_kind')) {
                $table->string('owner_kind', 30)->default('COMPANY')->after('company_id');
            }
            if (! Schema::hasColumn('erp_company_ownerships', 'owner_display_name')) {
                $table->string('owner_display_name', 200)->nullable()->after('owner_company_id');
            }
            if (! Schema::hasColumn('erp_company_ownerships', 'owner_identifier')) {
                $table->string('owner_identifier', 50)->nullable()->after('owner_display_name');
            }
        });

        // Allow null owner_company_id for external owners
        DB::statement('ALTER TABLE erp_company_ownerships ALTER COLUMN owner_company_id DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::table('erp_company_ownerships', function (Blueprint $table) {
            if (Schema::hasColumn('erp_company_ownerships', 'owner_identifier')) {
                $table->dropColumn('owner_identifier');
            }
            if (Schema::hasColumn('erp_company_ownerships', 'owner_display_name')) {
                $table->dropColumn('owner_display_name');
            }
            if (Schema::hasColumn('erp_company_ownerships', 'owner_kind')) {
                $table->dropColumn('owner_kind');
            }
        });
    }
};
