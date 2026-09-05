<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * inv_item_barcodes — barcodes / alternate SKUs for items (Owner: Inventory).
 * Per Inventory_Logistics_Module.md + platform rules (soft delete, audit, row_version).
 * Physical FK to inv_items only (same Bounded Context).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_item_barcodes', function (Blueprint $table) {
            $table->uuid('barcode_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('item_id');

            $table->string('barcode', 100);
            $table->string('barcode_type', 50)->default('EAN13');
            $table->string('sku', 100)->nullable();
            $table->boolean('is_primary')->default(false);

            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->foreign('item_id', 'fk_inv_item_barcodes_item')
                ->references('item_id')->on('inv_items')->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_inv_item_barcodes ON inv_item_barcodes(tenant_id, barcode) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_inv_item_barcodes_lookup ON inv_item_barcodes(item_id) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_inv_item_barcodes_tenant ON inv_item_barcodes(tenant_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_item_barcodes');
    }
};
