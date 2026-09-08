<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * L6-PS-08 – Cash/bank movements against payment schedules.
 * Physical FK to fin_payment_schedules allowed (same bounded context).
 * bank_account_id is logical ref to MasterData bank_accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_cash_transactions', function (Blueprint $table) {
            $table->uuid('cash_transaction_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('payment_schedule_id')->notNull();
            $table->uuid('bank_account_id')->notNull();
            $table->smallInteger('transaction_type')->notNull(); // 1 Receipt, 2 Payment
            $table->decimal('amount', 20, 4)->notNull();
            $table->string('payment_reference', 150)->nullable();
            $table->timestampTz('transaction_date')->notNull()->default(DB::raw('NOW()'));
            $table->smallInteger('status')->notNull()->default(2); // 1 Pending, 2 Cleared, 3 Rejected
            $table->uuid('accounting_voucher_id')->nullable();
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            $table->foreign('payment_schedule_id', 'fk_fin_cash_tx_schedule')
                ->references('payment_schedule_id')
                ->on('fin_payment_schedules')
                ->onDelete('restrict');
        });

        DB::statement('CREATE INDEX idx_fin_cash_tx_tenant ON fin_cash_transactions (tenant_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_fin_cash_tx_bank ON fin_cash_transactions (bank_account_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_fin_cash_tx_schedule ON fin_cash_transactions (payment_schedule_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_cash_transactions');
    }
};
