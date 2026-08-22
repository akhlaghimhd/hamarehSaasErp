<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_bom_items', function (Blueprint $table) {
            $table->uuid('bom_item_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // ارجاع منطقی به ماژول SaaS Platform
            $table->uuid('bom_id')->notNull(); // درون‌ماژولی
            $table->uuid('material_item_id')->notNull(); // ارجاع منطقی به Inventory Raw Material Item
            $table->decimal('quantity', 20, 4)->notNull()->default(1.0000);
            $table->decimal('scrap_percentage', 20, 4)->notNull()->default(0.0000);
            $table->text('notes')->nullable();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            // کلید خارجی فیزیکی به tenants حذف شد. فقط کلید درون‌ماژولی باقی می‌ماند:
            $table->foreign('bom_id')->references('bom_id')->on('mfg_boms')->onDelete('restrict');
        });

        DB::statement('CREATE INDEX idx_mfg_bom_items_bom ON mfg_bom_items(tenant_id, bom_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_bom_items');
    }
};