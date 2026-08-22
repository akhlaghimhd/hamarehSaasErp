<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_data_categories', function (Blueprint $table) {
            $table->uuid('master_data_category_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            
            $table->string('code', 100);
            $table->string('name', 200);
            $table->string('description', 500)->nullable();
            $table->boolean('is_system_category')->default(false);
            $table->smallInteger('status')->default(1);
            
            // فیلدهای حسابرسی و کنترل همزمانی
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->unique(['tenant_id', 'code'], 'uq_master_data_categories_tenant_code');
            $table->index('tenant_id', 'idx_master_data_categories_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data_categories');
    }
};