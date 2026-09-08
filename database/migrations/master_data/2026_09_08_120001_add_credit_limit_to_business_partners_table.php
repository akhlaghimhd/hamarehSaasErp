<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L6-PS-07 – Credit limit on tenant-owned Business Partner (Master Data).
 * Used by Sales Invoice post path for AR exposure control.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_partners', function (Blueprint $table) {
            $table->decimal('credit_limit', 20, 4)->notNull()->default(0.0000)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('business_partners', function (Blueprint $table) {
            $table->dropColumn('credit_limit');
        });
    }
};
