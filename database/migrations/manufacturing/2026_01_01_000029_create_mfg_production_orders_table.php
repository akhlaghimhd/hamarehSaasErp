<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_production_orders', function (Blueprint $table) {
            $table->uuid('production_order_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // ارجاع منطقی به ماژول SaaS Platform
            $table->string('order_number', 100)->notNull();
            $table->uuid('item_id')->notNull(); // ارجاع منطقی به Inventory Item
            $table->uuid('bom_id')->nullable(); // درون‌ماژولی
            $table->decimal('planned_quantity', 20, 4)->notNull()->default(0.0000);
            $table->decimal('produced_quantity', 20, 4)->notNull()->default(0.0000);
            $table->date('start_date')->notNull();
            $table->date('due_date')->notNull();
            $table->smallInteger('status')->notNull()->default(1); // 1: Draft, 2: Released, 3: In Progress, 4: Completed, 0: Cancelled

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            // کلید خارجی فیزیکی به tenants حذف شد. فقط کلید درون‌ماژولی باقی می‌ماند:
            $table->foreign('bom_id')->references('bom_id')->on('mfg_boms')->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_mfg_production_orders_number ON mfg_production_orders(tenant_id, order_number) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_mfg_production_orders_status ON mfg_production_orders(tenant_id, status) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_production_orders');
    }
};