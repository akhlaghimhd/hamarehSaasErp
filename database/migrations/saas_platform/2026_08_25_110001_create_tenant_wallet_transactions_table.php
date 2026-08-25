<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_wallet_transactions', function (Blueprint $table) {
            $table->uuid('wallet_transaction_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('wallet_id')->notNull();
            $table->smallInteger('transaction_type')->notNull();
            $table->decimal('amount', 20, 4)->notNull();
            $table->decimal('balance_after', 20, 4)->notNull();
            $table->string('description', 500)->nullable();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->notNull()->default(1);

            $table->foreign('wallet_id')
                  ->references('wallet_id')
                  ->on('tenant_wallets')
                  ->onDelete('restrict');
        });

        DB::statement('CREATE INDEX idx_tenant_wallet_tx_wallet ON tenant_wallet_transactions(wallet_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_wallet_transactions');
    }
};