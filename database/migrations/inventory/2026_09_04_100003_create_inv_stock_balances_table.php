<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * inv_stock_balances — live stock ledger per Inventory_Logistics_Module.md
 * Physical FKs within Inventory only. No soft delete (live ledger).
 * quantity_available is GENERATED ALWAYS AS (quantity_on_hand - quantity_reserved) STORED.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_stock_balances', function (Blueprint $table) {
            $table->uuid('stock_balance_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('warehouse_id');
            $table->uuid('location_id');
            $table->uuid('item_id');

            $table->decimal('quantity_on_hand', 20, 4)->default(0);
            $table->decimal('quantity_reserved', 20, 4)->default(0);

            $table->timestampTz('updated_at')->default(DB::raw('NOW()'));
            $table->bigInteger('row_version')->default(1);

            $table->foreign('warehouse_id', 'fk_inv_stock_balances_warehouse')
                ->references('warehouse_id')->on('inv_warehouses')->onDelete('restrict');
            $table->foreign('location_id', 'fk_inv_stock_balances_location')
                ->references('location_id')->on('inv_locations')->onDelete('restrict');
            $table->foreign('item_id', 'fk_inv_stock_balances_item')
                ->references('item_id')->on('inv_items')->onDelete('restrict');
        });

        // Generated column per architecture doc
        DB::statement('ALTER TABLE inv_stock_balances ADD COLUMN quantity_available NUMERIC(20,4) GENERATED ALWAYS AS (quantity_on_hand - quantity_reserved) STORED;');

        DB::statement('CREATE UNIQUE INDEX uq_inv_stock_balances ON inv_stock_balances(location_id, item_id);');
        DB::statement('CREATE INDEX idx_inv_stock_balances_tenant_lookup ON inv_stock_balances(tenant_id, warehouse_id, item_id);');
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_stock_balances');
    }
};
