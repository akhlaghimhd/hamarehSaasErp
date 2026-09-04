<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * inv_warehouses — per Inventory_Logistics_Module.md (Owner: Inventory)
 * Logical ref only: branch_id (Organization) — no physical FK across modules
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_warehouses', function (Blueprint $table) {
            $table->uuid('warehouse_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');

            // Logical reference to Organization Branch — no physical FK
            $table->uuid('branch_id');

            $table->string('code', 50);
            $table->string('name', 200);
            $table->boolean('is_bonded')->default(false);
            $table->smallInteger('status')->default(1);

            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);
        });

        DB::statement('CREATE UNIQUE INDEX uq_inv_warehouses_code ON inv_warehouses(tenant_id, code) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_inv_warehouses_tenant ON inv_warehouses(tenant_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_warehouses');
    }
};
