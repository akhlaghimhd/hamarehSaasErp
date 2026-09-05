<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * L6-PS-00 – Purchase Order Items
 * Logical reference to inv_items via item_id (no physical FK across modules).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->uuid('purchase_order_item_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('purchase_order_id')->notNull();
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

        DB::statement('
            ALTER TABLE purchase_order_items
            ADD CONSTRAINT fk_po_items_purchase_order
            FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(purchase_order_id)
            ON DELETE RESTRICT
        ');

        DB::statement('CREATE INDEX idx_po_items_order ON purchase_order_items (tenant_id, purchase_order_id) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_po_items_item ON purchase_order_items (tenant_id, item_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
