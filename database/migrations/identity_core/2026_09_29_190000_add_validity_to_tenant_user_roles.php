<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ID-W3-01 — Time-bounded role assignments.
 * null valid_from/valid_to = open-ended (current behavior).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenant_user_roles')) {
            return;
        }

        Schema::table('tenant_user_roles', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_user_roles', 'valid_from')) {
                $table->timestampTz('valid_from')->nullable()->after('tenant_role_id');
            }
            if (!Schema::hasColumn('tenant_user_roles', 'valid_to')) {
                $table->timestampTz('valid_to')->nullable()->after('valid_from');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tenant_user_roles')) {
            return;
        }

        Schema::table('tenant_user_roles', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_user_roles', 'valid_to')) {
                $table->dropColumn('valid_to');
            }
            if (Schema::hasColumn('tenant_user_roles', 'valid_from')) {
                $table->dropColumn('valid_from');
            }
        });
    }
};
