<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->uuid('uom_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id'); // ایزوله‌سازی چند مستأجری
            
            $table->string('code', 50);
            $table->string('name', 100);
            $table->smallInteger('decimal_places')->default(0);
            $table->decimal('conversion_factor', 20, 4)->default(1.0000); // اعمال اصلاحیه محاسباتی ADD
            $table->smallInteger('status')->default(1); // 1: Active, 2: Inactive
            
            // فیلدهای حسابرسی و همزمانی
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            // ایندکس‌ها
            $table->unique(['tenant_id', 'code'], 'uq_uom_tenant_code');
            $table->index('tenant_id', 'idx_uom_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units_of_measure');
    }
};