<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_production_routing', function (Blueprint $table) {
            $table->uuid('routing_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // ارجاع منطقی به ماژول Tenant Management
            $table->uuid('production_order_id')->notNull(); // درون‌ماژولی
            $table->uuid('work_center_id')->notNull(); // درون‌ماژولی
            $table->integer('operation_sequence')->notNull();
            $table->string('operation_name', 200)->notNull();
            $table->decimal('standard_setup_time_hours', 20, 4)->notNull()->default(0.0000);
            $table->decimal('standard_run_time_hours', 20, 4)->notNull()->default(0.0000);
            $table->smallInteger('status')->notNull()->default(1); // 1: Pending, 2: Active, 3: Completed

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            // کلید خارجی فیزیکی به tenants حذف شد. فقط کلیدهای درون‌ماژولی باقی می‌مانند:
            $table->foreign('production_order_id')->references('production_order_id')->on('mfg_production_orders')->onDelete('restrict');
            $table->foreign('work_center_id')->references('work_center_id')->on('mfg_work_centers')->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_mfg_routing_sequence ON mfg_production_routing(production_order_id, operation_sequence) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_mfg_routing_center ON mfg_production_routing(tenant_id, work_center_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_production_routing');
    }
};