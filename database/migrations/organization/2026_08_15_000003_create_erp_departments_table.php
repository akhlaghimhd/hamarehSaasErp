<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ۱. ساخت جدول بدون کلید خارجی خود-ارجاعی
        Schema::create('erp_departments', function (Blueprint $table) {
            $table->uuid('department_id')->primary();
            $table->uuid('tenant_id');
            
            // این FK مشکلی ندارد چون جدول erp_branches قبلا ساخته شده
            $table->foreignUuid('branch_id')->references('branch_id')->on('erp_branches')->onDelete('restrict');
            
            // فقط تعریف ستون (بدون ایجاد کلید خارجی در این مرحله)
            $table->uuid('parent_department_id')->nullable();
            
            $table->string('code', 50);
            $table->string('name', 200);
            
            // Logical Reference
            $table->uuid('manager_user_id')->nullable(); 
            
            $table->boolean('is_active')->default(true);

            // Audit Fields
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index('branch_id', 'idx_erp_departments_branch');
            $table->index('parent_department_id', 'idx_erp_departments_parent');
            $table->index(['tenant_id', 'manager_user_id'], 'idx_erp_departments_manager');
        });

        // ۲. اضافه کردن کلید خارجی خود-ارجاعی پس از اتمام ساخت جدول
        Schema::table('erp_departments', function (Blueprint $table) {
            $table->foreign('parent_department_id')
                  ->references('department_id')->on('erp_departments')
                  ->onDelete('restrict');
        });

        // ۳. ساخت ایندکس یونیک
        DB::statement('CREATE UNIQUE INDEX uq_erp_departments_code ON erp_departments(tenant_id, branch_id, code) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_departments');
    }
};