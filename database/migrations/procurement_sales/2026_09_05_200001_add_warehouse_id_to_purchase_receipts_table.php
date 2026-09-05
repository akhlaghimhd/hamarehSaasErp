<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * L6-PS-03 – Align purchase_receipts with ADD-06 / Table Definitions:
 * warehouse_id is a logical reference to inv_warehouses (no physical FK across modules).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->uuid('warehouse_id')->nullable()->after('supplier_id');
        });

        DB::statement('CREATE INDEX idx_pur_receipts_warehouse ON purchase_receipts (tenant_id, warehouse_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_pur_receipts_warehouse');
        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->dropColumn('warehouse_id');
        });
    }
};
