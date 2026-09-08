<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/** L6-PS-09 – Requisition line items (separate table; logical item_id). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requisition_items', function (Blueprint $table) {
            $table->uuid('requisition_item_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('requisition_id')->notNull();
            $table->uuid('item_id')->notNull();
            $table->decimal('quantity', 20, 4)->notNull();
            $table->decimal('estimated_unit_price', 20, 4)->notNull()->default(0);
            $table->string('uom_code', 20)->nullable();
            $table->integer('line_number')->notNull()->default(1);
            $table->string('description', 500)->nullable();
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            $table->foreign('requisition_id', 'fk_req_items_header')
                ->references('requisition_id')->on('purchase_requisitions')->onDelete('restrict');
        });

        DB::statement('CREATE INDEX idx_purchase_requisition_items_header ON purchase_requisition_items (requisition_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requisition_items');
    }
};
