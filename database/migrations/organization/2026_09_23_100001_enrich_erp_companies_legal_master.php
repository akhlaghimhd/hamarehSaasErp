<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P0-01 … ORG-P0-06 — Legal entity master enrichment on erp_companies
 *
 * Adds: legal_name, trade_name, company_type, tax_identifier, national_id,
 * vat_registration, registration_date, registration_place, incorporation_country_id, status
 * Backfills: legal_name from name; status from is_active (1=Active, 2=Suspended)
 * Keeps is_active for backward compatibility (synced with status on write).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_companies', function (Blueprint $table) {
            $table->string('legal_name', 200)->nullable()->after('name');
            $table->string('trade_name', 200)->nullable()->after('legal_name');
            $table->smallInteger('company_type')->nullable()->after('trade_name');
            // tax_identifier: canonical tax id; economic_code retained for legacy
            $table->string('tax_identifier', 100)->nullable()->after('economic_code');
            $table->string('national_id', 50)->nullable()->after('tax_identifier');
            $table->string('vat_registration', 100)->nullable()->after('national_id');
            $table->date('registration_date')->nullable()->after('registration_number');
            $table->string('registration_place', 200)->nullable()->after('registration_date');
            $table->uuid('incorporation_country_id')->nullable()->after('registration_place');
            // 1=Active, 2=Suspended, 3=Dissolving, 4=Dissolved
            $table->smallInteger('status')->default(1)->after('is_active');
        });

        // Backfill existing rows
        DB::statement("
            UPDATE erp_companies
            SET legal_name = name
            WHERE legal_name IS NULL
        ");

        DB::statement("
            UPDATE erp_companies
            SET status = CASE WHEN is_active = true THEN 1 ELSE 2 END
            WHERE status IS NULL OR status = 1
        ");

        // Enforce NOT NULL on legal_name after backfill
        DB::statement('ALTER TABLE erp_companies ALTER COLUMN legal_name SET NOT NULL');
        DB::statement('ALTER TABLE erp_companies ALTER COLUMN status SET NOT NULL');
        DB::statement('ALTER TABLE erp_companies ALTER COLUMN status SET DEFAULT 1');
    }

    public function down(): void
    {
        Schema::table('erp_companies', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name',
                'trade_name',
                'company_type',
                'tax_identifier',
                'national_id',
                'vat_registration',
                'registration_date',
                'registration_place',
                'incorporation_country_id',
                'status',
            ]);
        });
    }
};
