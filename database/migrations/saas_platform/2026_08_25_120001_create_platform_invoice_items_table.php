<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_invoice_items', function (Blueprint $table) {
            $table->uuid('invoice_item_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('invoice_id')->notNull();
            $table->string('item_type', 50)->notNull();
            $table->uuid('reference_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->decimal('amount', 20, 4)->notNull();

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

        DB::statement('CREATE INDEX idx_platform_invoice_items_invoice ON platform_invoice_items(invoice_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_invoice_items');
    }
};