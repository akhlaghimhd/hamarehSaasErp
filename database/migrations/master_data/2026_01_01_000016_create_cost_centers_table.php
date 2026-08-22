<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ۱. ساخت اولیه جدول مراکز هزینه (cost_centers)
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->uuid('cost_center_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('company_id')->nullable();
            $table->uuid('department_id')->nullable();
            $table->uuid('parent_cost_center_id')->nullable();
            
            $table->string('code', 50);
            $table->string('name', 200);
            $table->smallInteger('status')->default(1);
            
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            // ایندکس‌های یکتا و بهینه‌سازی
            $table->unique(['tenant_id', 'code'], 'uq_cost_centers_tenant_code');
            $table->index('tenant_id', 'idx_cost_centers_tenant');
            $table->index('company_id', 'idx_cost_centers_company');
            $table->index('department_id', 'idx_cost_centers_department');
            $table->index('parent_cost_center_id', 'idx_cost_centers_parent');
        });

        // ۲. افزودن کلید خارجی Self-Referencing پس از ایجاد کامل جدول
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->foreign('parent_cost_center_id')
                  ->references('cost_center_id')
                  ->on('cost_centers')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_centers');
    }
};