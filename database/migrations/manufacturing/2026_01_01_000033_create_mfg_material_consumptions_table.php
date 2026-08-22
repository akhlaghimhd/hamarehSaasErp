<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_material_consumptions', function (Blueprint $table) {
            $table->uuid('consumption_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // ارجاع منطقی به ماژول Tenant Management
            $table->uuid('production_order_id')->notNull(); // درون ماژولی
            $table->uuid('bom_item_id')->nullable(); // درون ماژولی
            $table->uuid('material_item_id')->notNull(); // ارجاع منطقی به Inventory
            $table->uuid('inventory_document_id')->nullable(); // ارجاع منطقی به Inventory Documents
            $table->decimal('planned_quantity', 20, 4)->notNull()->default(0.0000);
            $table->decimal('actual_quantity', 20, 4)->notNull()->default(0.0000);
            
            // ستون محاسبه خودکار انحراف مصرف مواد با رعایت دقت عددی NUMERIC(20,4)
            $table->decimal('variance_quantity', 20, 4)->storedAs('actual_quantity - planned_quantity');
            
            $table->timestampTz('consumption_date')->notNull()->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->uuid('consumed_by')->nullable();
            $table->smallInteger('status')->notNull()->default(1); // 1: Registered, 2: Posted, 0: Cancelled

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            // کلید خارجی فیزیکی به tenants حذف شد. فقط کلیدهای درون‌ماژولی Manufacturing باقی می‌مانند:
            $table->foreign('production_order_id')->references('production_order_id')->on('mfg_production_orders')->onDelete('restrict');
            $table->foreign('bom_item_id')->references('bom_item_id')->on('mfg_bom_items')->onDelete('restrict');
        });

        DB::statement('CREATE INDEX idx_mfg_consumptions_order ON mfg_material_consumptions(tenant_id, production_order_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_material_consumptions');
    }
};