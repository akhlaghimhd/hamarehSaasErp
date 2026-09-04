<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * inv_stock_batches — per Inventory_Logistics_Module.md (Owner: Inventory)
 * Physical FK to inv_items (same Bounded Context — Rule 2.4)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_stock_batches', function (Blueprint $table) {
            $table->uuid('batch_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('item_id');

            $table->string('batch_number', 100);
            $table->decimal('quantity_produced', 20, 4);
            $table->decimal('quantity_remaining', 20, 4);
            $table->date('production_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->smallInteger('qc_status')->default(1); // 1: Pending, 2: Approved, 3: Quarantined

            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->foreign('item_id', 'fk_inv_stock_batches_item')
                ->references('item_id')->on('inv_items')->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_inv_stock_batches ON inv_stock_batches(tenant_id, item_id, batch_number) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_inv_stock_batches_expiration ON inv_stock_batches(expiration_date) WHERE qc_status = 1;');
        DB::statement('CREATE INDEX idx_inv_stock_batches_tenant ON inv_stock_batches(tenant_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_stock_batches');
    }
};
