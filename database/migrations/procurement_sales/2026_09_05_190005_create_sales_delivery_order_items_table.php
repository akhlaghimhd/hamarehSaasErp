<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/** L6-PS-00 – Sales Delivery Order Items (FK to delivery_order_id) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_delivery_order_items', function (Blueprint $table) {
            $table->uuid('sales_delivery_order_item_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('delivery_order_id')->notNull();
            $table->uuid('sales_order_item_id')->nullable();
            $table->uuid('item_id')->notNull();
            $table->decimal('ordered_quantity', 20, 4)->nullable();
            $table->decimal('delivered_quantity', 20, 4)->notNull();
            $table->decimal('unit_price', 20, 4)->notNull()->default(0.0000);
            $table->decimal('total_price', 20, 4)->notNull()->default(0.0000);
            $table->string('uom_code', 30)->nullable();
            $table->unsignedInteger('line_number')->notNull()->default(1);
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });
        DB::statement('ALTER TABLE sales_delivery_order_items ADD CONSTRAINT fk_sdo_items_delivery FOREIGN KEY (delivery_order_id) REFERENCES sales_delivery_orders(delivery_order_id) ON DELETE RESTRICT');
        DB::statement('CREATE INDEX idx_sdo_items_delivery ON sales_delivery_order_items (tenant_id, delivery_order_id) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_sdo_items_item ON sales_delivery_order_items (tenant_id, item_id) WHERE deleted_at IS NULL;');
    }
    public function down(): void { Schema::dropIfExists('sales_delivery_order_items'); }
};
