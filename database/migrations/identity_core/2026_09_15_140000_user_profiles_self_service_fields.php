<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-service profile policy:
 * - description = display bio under avatar
 * - pending_address + address_change_status for manager approval flow
 * - gender remains 1=Male, 2=Female only (app-level)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('user_profiles', 'pending_address')) {
                $table->text('pending_address')->nullable()->after('address');
            }
            if (!Schema::hasColumn('user_profiles', 'address_change_status')) {
                // 0=none, 1=pending, 2=approved, 3=rejected
                $table->smallInteger('address_change_status')->default(0)->after('pending_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('user_profiles', 'address_change_status')) {
                $table->dropColumn('address_change_status');
            }
            if (Schema::hasColumn('user_profiles', 'pending_address')) {
                $table->dropColumn('pending_address');
            }
        });
    }
};
