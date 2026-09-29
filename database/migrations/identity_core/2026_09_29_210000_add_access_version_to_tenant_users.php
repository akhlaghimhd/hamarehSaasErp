<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ID-W3-03 — Monotonic access_version for continuous access re-evaluation.
 * Bumped when roles/scopes/privileged grants change; clients can detect stale sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenant_users')) {
            return;
        }

        Schema::table('tenant_users', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_users', 'access_version')) {
                $table->unsignedBigInteger('access_version')->default(1)->after('is_owner');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tenant_users')) {
            return;
        }

        Schema::table('tenant_users', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_users', 'access_version')) {
                $table->dropColumn('access_version');
            }
        });
    }
};
