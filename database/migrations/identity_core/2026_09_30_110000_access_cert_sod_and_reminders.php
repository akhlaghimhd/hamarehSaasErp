<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ID-W2-01b — SoD snapshot on cert items + periodic campaign reminder state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_access_cert_items', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_access_cert_items', 'sod_has_block')) {
                $table->boolean('sod_has_block')->default(false)->after('role_ids_snapshot');
            }
            if (!Schema::hasColumn('tenant_access_cert_items', 'sod_has_warn')) {
                $table->boolean('sod_has_warn')->default(false)->after('sod_has_block');
            }
            if (!Schema::hasColumn('tenant_access_cert_items', 'sod_conflicts')) {
                $table->jsonb('sod_conflicts')->nullable()->after('sod_has_warn');
            }
        });

        if (!Schema::hasTable('tenant_access_cert_settings')) {
            Schema::create('tenant_access_cert_settings', function (Blueprint $table) {
                $table->uuid('tenant_id')->primary();
                $table->unsignedSmallInteger('preferred_cadence_months')->default(3);
                $table->boolean('reminders_enabled')->default(true);
                $table->timestampTz('last_reminder_at')->nullable();
                $table->unsignedSmallInteger('last_reminder_cadence_months')->nullable();
                $table->timestampsTz();
                $table->unsignedBigInteger('row_version')->default(1);
            });

            DB::statement('ALTER TABLE tenant_access_cert_settings ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE tenant_access_cert_settings FORCE ROW LEVEL SECURITY');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_access_cert_settings');
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON tenant_access_cert_settings
                FOR ALL
                USING (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenant_access_cert_settings')) {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_access_cert_settings');
            Schema::dropIfExists('tenant_access_cert_settings');
        }

        Schema::table('tenant_access_cert_items', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_access_cert_items', 'sod_conflicts')) {
                $table->dropColumn('sod_conflicts');
            }
            if (Schema::hasColumn('tenant_access_cert_items', 'sod_has_warn')) {
                $table->dropColumn('sod_has_warn');
            }
            if (Schema::hasColumn('tenant_access_cert_items', 'sod_has_block')) {
                $table->dropColumn('sod_has_block');
            }
        });
    }
};
