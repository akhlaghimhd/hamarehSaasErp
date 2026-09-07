<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L6-PS-05 – Logical warehouse ref for stock reservation on Sales Order confirm.
 * No physical FK to inv_warehouses (cross-module boundary).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->uuid('warehouse_id')->nullable()->after('currency_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('warehouse_id');
        });
    }
};
