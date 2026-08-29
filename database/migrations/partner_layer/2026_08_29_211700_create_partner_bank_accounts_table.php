<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * P3-D10 — partner_bank_accounts (Database Layer 3 SSOT).
 * partner_id: physical FK within PartnerLayer.
 * uq_partner_bank_shaba: unique active SHABA per SSOT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_bank_accounts', function (Blueprint $table) {
            $table->uuid('partner_bank_account_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('partner_id');
            $table->string('bank_name', 150);
            $table->string('account_number', 50)->nullable();
            $table->string('shaba_number', 50);
            $table->string('card_number', 16)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->foreign('partner_id')
                ->references('partner_id')
                ->on('partners')
                ->onDelete('restrict');
        });

        DB::statement(
            'CREATE UNIQUE INDEX uq_partner_bank_shaba ON partner_bank_accounts (shaba_number) WHERE is_active = TRUE'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_bank_accounts');
    }
};
