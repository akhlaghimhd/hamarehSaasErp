<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/** L6-PS-00 – Return Order Items */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_order_items', function (Blueprint $table) {
            $table->uuid('return_order_item_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('return_order_id')->notNull();
            $table->uuid('item_id')->notNull();
            $table->decimal('quantity', 20, 4)->notNull();
            $table->decimal('unit_price', 20, 4)->notNull()->default(0.0000);
            $table->decimal('total_price', 20, 4)->notNull()->default(0.0000);
            $table->string('uom_code', 30)->nullable();
            $table->unsignedInteger('line_number')->notNull()->default(1);
            $table->text('reason')->nullable();
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });
        DB::statement('ALTER TABLE return_order_items ADD CONSTRAINT fk_ro_items_return_order FOREIGN KEY (return_order_id) REFERENCES return_orders(return_order_id) ON DELETE RESTRICT');
        DB::statement('CREATE INDEX idx_ro_items_return ON return_order_items (tenant_id, return_order_id) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_ro_items_item ON return_order_items (tenant_id, item_id) WHERE deleted_at IS NULL;');
    }
    public function down(): void { Schema::dropIfExists('return_order_items'); }
};
