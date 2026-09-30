<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temporary deactivation window for SoD rules.
 * When is_active=false and inactive_until is set, UI shows "غیرفعال تا …".
 * list/evaluate auto-reactivate when inactive_until is in the past.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenant_sod_rules')) {
            return;
        }
        if (!Schema::hasColumn('tenant_sod_rules', 'inactive_until')) {
            Schema::table('tenant_sod_rules', function (Blueprint $table) {
                $table->timestampTz('inactive_until')->nullable()->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenant_sod_rules') && Schema::hasColumn('tenant_sod_rules', 'inactive_until')) {
            Schema::table('tenant_sod_rules', function (Blueprint $table) {
                $table->dropColumn('inactive_until');
            });
        }
    }
};
