<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * inv_locations — per Inventory_Logistics_Module.md (Owner: Inventory)
 * Physical FK to inv_warehouses and self (same Bounded Context — Rule 2.4)
 * Self-referencing parent FK is added AFTER create so PostgreSQL sees the PK constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_locations', function (Blueprint $table) {
            $table->uuid('location_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('warehouse_id');
            $table->uuid('parent_location_id')->nullable();
            $table->uuid('tenant_id');

            $table->string('code', 50);
            $table->string('name', 200);
            $table->string('aisle', 50)->nullable();
            $table->string('rack', 50)->nullable();
            $table->string('shelf', 50)->nullable();
            $table->smallInteger('status')->default(1);

            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            // Physical FK to warehouse (same Bounded Context)
            $table->foreign('warehouse_id', 'fk_inv_locations_warehouse')
                ->references('warehouse_id')->on('inv_warehouses')->onDelete('restrict');
        });

        // Self-referencing FK must be added after the table (and its PK) exists
        Schema::table('inv_locations', function (Blueprint $table) {
            $table->foreign('parent_location_id', 'fk_inv_locations_parent')
                ->references('location_id')->on('inv_locations')->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_inv_locations_code ON inv_locations(warehouse_id, code) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_inv_locations_parent ON inv_locations(parent_location_id) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_inv_locations_tenant ON inv_locations(tenant_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_locations');
    }
};
