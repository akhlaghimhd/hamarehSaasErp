<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_transactions', function (Blueprint $table) {
            $table->uuid('transaction_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('invoice_id')->notNull();
            $table->string('gateway', 100)->nullable();
            $table->string('transaction_number', 200)->nullable();
            $table->decimal('amount', 20, 4)->notNull();
            $table->smallInteger('status')->notNull()->default(1);
            $table->timestampTz('transaction_date')->nullable();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->notNull()->default(1);

            $table->foreign('invoice_id')
                  ->references('invoice_id')
                  ->on('platform_invoices')
                  ->onDelete('restrict');
        });

        DB::statement('CREATE INDEX idx_platform_transactions_invoice ON platform_transactions(invoice_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_transactions');
    }
};