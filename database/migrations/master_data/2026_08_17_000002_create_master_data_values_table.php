<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_data_values', function (Blueprint $table) {
            $table->uuid('master_data_value_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->notNull();
            $table->uuid('master_data_category_id');
            $table->string('code', 100)->notNull();
            $table->string('name', 200)->notNull();
            $table->text('description')->nullable();
            
            // فیلد داده‌های اضافی برای ایندکس GIN
            $table->jsonb('extra_data')->nullable();

            $table->smallInteger('status')->default(1);

            // ستون‌های استاندارد Audit
            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            
            $table->bigInteger('row_version')->default(1);

            // کلید خارجی
            $table->foreign('master_data_category_id', 'fk_master_data_values_category')
                  ->references('master_data_category_id')
                  ->on('master_data_categories')
                  ->onDelete('restrict');

            // ایندکس‌های استاندارد
            $table->unique(['tenant_id', 'master_data_category_id', 'code'], 'uq_master_data_values_tenant_code')
                  ->whereNull('deleted_at');
                  
            $table->index(['tenant_id', 'master_data_category_id'], 'idx_master_data_values_tenant_category')
                  ->whereNull('deleted_at');
        });

        // اعمال ایندکس GIN به صورت ایمن پس از ساخت کامل جدول
        DB::statement('CREATE INDEX idx_master_data_values_extra_data_gin ON master_data_values USING GIN (extra_data)');
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data_values');
    }
};