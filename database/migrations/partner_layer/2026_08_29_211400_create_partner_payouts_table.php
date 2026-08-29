<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * P3-D7 — partner_payouts (Database Layer 3 SSOT + ALTER extensions).
 *
 * Physical FK: partner_id (same bounded context).
 * Logical refs (no FK): currency_id, bank_account_id.
 * bank_account_id will reference partner_bank_accounts after P3-D10; kept logical until then.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_payouts', function (Blueprint $table) {
            $table->uuid('payout_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('partner_id');
            $table->string('payout_number', 100);
            $table->decimal('total_amount', 20, 4);
            $table->uuid('currency_id'); // Logical reference
            $table->uuid('bank_account_id')->nullable(); // Logical / later same-BC ref
            $table->timestampTz('payout_date')->nullable();
            $table->string('payment_reference', 200)->nullable();
            $table->smallInteger('status')->default(1);
            $table->string('description', 500)->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->foreign('partner_id')
                ->references('partner_id')
                ->on('partners')
                ->onDelete('restrict');
        });

        DB::statement(
            'CREATE UNIQUE INDEX uq_partner_payouts_number ON partner_payouts (payout_number) WHERE deleted_at IS NULL'
        );
        DB::statement(
            'CREATE INDEX idx_partner_payouts_partner ON partner_payouts (partner_id) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_payouts');
    }
};
