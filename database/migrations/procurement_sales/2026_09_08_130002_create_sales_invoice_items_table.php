<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/** L6-PS-07 – Sales Invoice line items */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoice_items', function (Blueprint $table) {
            $table->uuid('sales_invoice_item_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('sales_invoice_id')->notNull();
            $table->uuid('item_id')->notNull();
            $table->decimal('quantity', 20, 4)->notNull();
            $table->decimal('unit_price', 20, 4)->notNull()->default(0.0000);
            $table->decimal('discount_amount', 20, 4)->notNull()->default(0.0000);
            $table->decimal('tax_amount', 20, 4)->notNull()->default(0.0000);
            $table->decimal('total_price', 20, 4)->notNull()->default(0.0000);
            $table->uuid('tax_definition_id')->nullable();
            $table->string('uom_code', 30)->nullable();
            $table->unsignedInteger('line_number')->notNull()->default(1);
            $table->text('description')->nullable();
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('ALTER TABLE sales_invoice_items ADD CONSTRAINT fk_sinv_items_invoice FOREIGN KEY (sales_invoice_id) REFERENCES sales_invoices(sales_invoice_id) ON DELETE RESTRICT');
        DB::statement('CREATE INDEX idx_sinv_items_invoice ON sales_invoice_items (tenant_id, sales_invoice_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_sinv_items_item ON sales_invoice_items (tenant_id, item_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_items');
    }
};
