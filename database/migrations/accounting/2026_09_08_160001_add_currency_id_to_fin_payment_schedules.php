<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** L6-PS-08b – currency_id on payment schedules (docs ADD-06). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fin_payment_schedules', function (Blueprint $table) {
            $table->uuid('currency_id')->nullable()->after('source_document_id');
        });
    }

    public function down(): void
    {
        Schema::table('fin_payment_schedules', function (Blueprint $table) {
            $table->dropColumn('currency_id');
        });
    }
};
