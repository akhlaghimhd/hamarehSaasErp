<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfg_work_centers', function (Blueprint $table) {
            $table->uuid('work_center_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // ارجاع منطقی به ماژول SaaS Platform
            $table->string('code', 50)->notNull();
            $table->string('name', 200)->notNull();
            $table->decimal('capacity_hours_per_day', 20, 4)->notNull()->default(8.0000);
            $table->decimal('efficiency_percentage', 20, 4)->notNull()->default(100.0000);
            $table->decimal('cost_per_hour', 20, 4)->notNull()->default(0.0000);
            $table->smallInteger('status')->notNull()->default(1); // 1: Active, 2: Inactive, 3: Maintenance

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            // کلید خارجی فیزیکی به tenants (خارج از ماژول) حذف شد.
        });

        DB::statement('CREATE UNIQUE INDEX uq_mfg_work_centers_code ON mfg_work_centers(tenant_id, code) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('mfg_work_centers');
    }
};