<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-D2 — Align partner_users with Database Layer 3 SSOT.
 *
 * Adds:
 * - is_primary BOOLEAN NOT NULL DEFAULT FALSE
 * - status SMALLINT NOT NULL DEFAULT 1
 *
 * user_id remains a logical reference to IdentityCore (no physical FK).
 * Soft delete unique index uq_partner_users already exists from base migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_users', function (Blueprint $table) {
            if (!Schema::hasColumn('partner_users', 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('user_id');
            }

            if (!Schema::hasColumn('partner_users', 'status')) {
                $table->smallInteger('status')->default(1)->after('is_primary');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partner_users', function (Blueprint $table) {
            if (Schema::hasColumn('partner_users', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('partner_users', 'is_primary')) {
                $table->dropColumn('is_primary');
            }
        });
    }
};
