<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * inv_items — per Inventory_Logistics_Module.md (Owner: Inventory)
 * Logical refs only: item_group_id, uom_id (no physical FK across modules)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto;');

        Schema::create('inv_items', function (Blueprint $table) {
            $table->uuid('item_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');

            // Logical references (Master Data / supporting entities) — no physical FK
            $table->uuid('item_group_id');
            $table->uuid('uom_id');

            $table->string('code', 100);
            $table->string('name', 300);
            $table->string('description', 500)->nullable();
            $table->smallInteger('item_type')->default(1); // 1: Stockable, 2: Service, 3: Expense
            $table->smallInteger('valuation_method')->default(1); // 1: FIFO, 2: Moving Average
            $table->jsonb('extra_attributes')->nullable();
            $table->smallInteger('status')->default(1);

            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);
        });

        DB::statement('CREATE UNIQUE INDEX uq_inv_items_code ON inv_items(tenant_id, code) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_inv_items_extra_attributes ON inv_items USING GIN (extra_attributes);');
        DB::statement('CREATE INDEX idx_inv_items_tenant ON inv_items(tenant_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_items');
    }
};
