<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ID-W1-03 — MFA foundation columns on user_credentials (platform user, no tenant_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_credentials', function (Blueprint $table) {
            // Encrypted TOTP secret (Laravel Crypt); null until setup started
            if (!Schema::hasColumn('user_credentials', 'totp_secret')) {
                $table->text('totp_secret')->nullable()->after('two_factor_enabled');
            }
            if (!Schema::hasColumn('user_credentials', 'two_factor_confirmed_at')) {
                $table->timestampTz('two_factor_confirmed_at')->nullable()->after('totp_secret');
            }
            // JSON array of hashed recovery codes (one-time use)
            if (!Schema::hasColumn('user_credentials', 'recovery_codes')) {
                $table->json('recovery_codes')->nullable()->after('two_factor_confirmed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_credentials', function (Blueprint $table) {
            $cols = ['totp_secret', 'two_factor_confirmed_at', 'recovery_codes'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('user_credentials', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
