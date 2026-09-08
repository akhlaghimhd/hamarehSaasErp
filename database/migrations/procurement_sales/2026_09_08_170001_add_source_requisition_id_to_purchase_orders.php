<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/** L6-PS-09b – logical link: approved requisition → purchase order. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_orders')) {
            return;
        }
        if (Schema::hasColumn('purchase_orders', 'source_requisition_id')) {
            return;
        }
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->uuid('source_requisition_id')->nullable()->after('currency_id');
        });
        DB::statement('CREATE INDEX IF NOT EXISTS idx_purchase_orders_source_req ON purchase_orders (tenant_id, source_requisition_id) WHERE deleted_at IS NULL AND source_requisition_id IS NOT NULL');
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_orders', 'source_requisition_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('source_requisition_id');
            });
        }
    }
};
