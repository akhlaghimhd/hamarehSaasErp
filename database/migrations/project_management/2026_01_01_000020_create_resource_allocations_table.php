<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_allocations', function (Blueprint $table) {
            $table->uuid('allocation_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->notNull()->index(); // ارجاع منطقی به ماژول Tenant Management
            $table->uuid('task_id')->notNull(); // درون‌ماژولی
            $table->smallInteger('resource_type')->notNull(); // 1: Human (Employee), 2: Equipment/Machine, 3: Material
            $table->uuid('resource_id')->notNull(); // ارجاع منطقی و پویا به منابع
            $table->decimal('allocated_quantity', 20, 4)->notNull()->default(1.0000);
            $table->date('start_date')->notNull();
            $table->date('end_date')->notNull();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            // کلید خارجی فیزیکی به tenants (خارج از ماژول) حذف شد. 
            // فقط کلید درون‌ماژولی به project_tasks حفظ می‌شود:
            $table->foreign('task_id')->references('task_id')->on('project_tasks')->onDelete('restrict');
        });

        DB::statement('CREATE INDEX idx_resource_allocations_task ON resource_allocations(task_id) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_resource_allocations_resource ON resource_allocations(tenant_id, resource_type, resource_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_allocations');
    }
};