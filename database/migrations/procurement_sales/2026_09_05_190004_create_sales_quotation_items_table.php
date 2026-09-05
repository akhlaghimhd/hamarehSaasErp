<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/** L6-PS-00 – Sales Quotation Items */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_quotation_items', function (Blueprint $table) {
            $table->uuid('sales_quotation_item_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('quotation_id')->notNull();
            $table->uuid('item_id')->notNull();
            $table->decimal('quantity', 20, 4)->notNull();
            $table->decimal('unit_price', 20, 4)->notNull()->default(0.0000);
            $table->decimal('discount_amount', 20, 4)->notNull()->default(0.0000);
            $table->decimal('tax_amount', 20, 4)->notNull()->default(0.0000);
            $table->decimal('total_price', 20, 4)->notNull()->default(0.0000);
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
        DB::statement('ALTER TABLE sales_quotation_items ADD CONSTRAINT fk_sq_items_quotation FOREIGN KEY (quotation_id) REFERENCES sales_quotations(quotation_id) ON DELETE RESTRICT');
        DB::statement('CREATE INDEX idx_sq_items_quotation ON sales_quotation_items (tenant_id, quotation_id) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_sq_items_item ON sales_quotation_items (tenant_id, item_id) WHERE deleted_at IS NULL;');
    }
    public function down(): void { Schema::dropIfExists('sales_quotation_items'); }
};
