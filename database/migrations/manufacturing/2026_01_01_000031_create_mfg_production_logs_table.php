<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_production_logs', function (Blueprint $table) {
            $table->uuid('production_log_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // ارجاع منطقی به ماژول Tenant Management
            $table->uuid('production_order_id')->notNull(); // درون‌ماژولی
            $table->uuid('routing_id')->nullable(); // درون‌ماژولی
            $table->smallInteger('log_type')->notNull(); // 1: Material Issue/Consumption, 2: Labor/Machine Time Log, 3: Scrap/Zayeat Log
            $table->uuid('item_id')->nullable(); // ارجاع منطقی به ماژول Inventory
            $table->decimal('quantity_consumed', 20, 4)->notNull()->default(0.0000);
            $table->decimal('hours_spent', 20, 4)->notNull()->default(0.0000);
            $table->timestampTz('logged_at')->notNull()->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            // کلید خارجی فیزیکی به tenants حذف شد. فقط کلیدهای درون‌ماژولی باقی می‌مانند:
            $table->foreign('production_order_id')->references('production_order_id')->on('mfg_production_orders')->onDelete('restrict');
            $table->foreign('routing_id')->references('routing_id')->on('mfg_production_routing')->onDelete('restrict');
        });

        DB::statement('CREATE INDEX idx_mfg_logs_order ON mfg_production_logs(tenant_id, production_order_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_production_logs');
    }
};