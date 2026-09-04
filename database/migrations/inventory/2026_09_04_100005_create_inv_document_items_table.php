<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * inv_document_items — line items of inventory documents per Inventory_Logistics_Module.md
 * Physical FKs within Inventory only. total_cost is GENERATED ALWAYS AS (quantity * unit_cost) STORED.
 * No soft delete (document lines follow parent document lifecycle).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_document_items', function (Blueprint $table) {
            $table->uuid('document_item_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('document_id');
            $table->uuid('tenant_id');
            $table->uuid('item_id');
            $table->uuid('from_location_id')->nullable();
            $table->uuid('to_location_id')->nullable();
            $table->string('batch_number', 100)->nullable();

            $table->decimal('quantity', 20, 4);
            $table->decimal('unit_cost', 20, 4)->default(0);
            $table->integer('sort_order')->default(0);

            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
            $table->timestampTz('updated_at')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->foreign('document_id', 'fk_inv_document_items_document')
                ->references('document_id')->on('inv_documents')->onDelete('restrict');
            $table->foreign('item_id', 'fk_inv_document_items_item')
                ->references('item_id')->on('inv_items')->onDelete('restrict');
            $table->foreign('from_location_id', 'fk_inv_document_items_from_location')
                ->references('location_id')->on('inv_locations')->onDelete('restrict');
            $table->foreign('to_location_id', 'fk_inv_document_items_to_location')
                ->references('location_id')->on('inv_locations')->onDelete('restrict');
        });

        DB::statement('ALTER TABLE inv_document_items ADD COLUMN total_cost NUMERIC(20,4) GENERATED ALWAYS AS (quantity * unit_cost) STORED;');
        DB::statement('CREATE INDEX idx_inv_document_items_parent ON inv_document_items(document_id);');
        DB::statement('CREATE INDEX idx_inv_document_items_tenant ON inv_document_items(tenant_id);');
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_document_items');
    }
};
