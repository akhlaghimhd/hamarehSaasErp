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
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('employee_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // فقط ایندکس برای عملکرد RLS و تفکیک مستأجر (بدون کلید خارجی فیزیکی به tenants)
            $table->uuid('business_partner_id')->nullable(); // Logical Reference to Master Data Business Partners
            $table->uuid('user_id')->nullable(); // Logical Reference to Identity Core Users
            $table->string('employee_code', 50)->notNull();
            $table->smallInteger('employment_type')->notNull()->default(1); // 1: Full Time, 2: Part Time, 3: Contract
            $table->date('hire_date')->notNull();
            $table->date('termination_date')->nullable();
            $table->string('job_title', 150)->nullable();
            $table->uuid('department_id')->nullable(); // Logical Reference to Departments
            $table->uuid('branch_id')->nullable(); // Logical Reference to Branches
            $table->smallInteger('status')->notNull()->default(1); // 1: Active, 2: Suspended, 3: Terminated

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        // ایجاد ایندکس‌های بهینه‌سازی و یکتایی بر اساس استانداردهای عملکردی
        DB::statement('CREATE UNIQUE INDEX uq_employees_tenant_code ON employees(tenant_id, employee_code) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_employees_business_partner ON employees(tenant_id, business_partner_id) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_employees_user_lookup ON employees(tenant_id, user_id) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_employees_department ON employees(tenant_id, department_id) WHERE deleted_at IS NULL;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};