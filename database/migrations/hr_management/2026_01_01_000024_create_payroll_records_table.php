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
        Schema::create('payroll_records', function (Blueprint $table) {
            $table->uuid('payroll_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // ایندکس برای تفکیک مستأجر (بدون کلید خارجی فیزیکی به tenants)
            $table->uuid('employee_id')->index(); // ارجاع منطقی به جدول employees (بدون FK سخت)
            $table->uuid('fiscal_period_id')->notNull(); // Logical Reference to Financial Fiscal Period
            $table->decimal('base_salary', 20, 4)->notNull()->default(0.0000);
            $table->decimal('allowances_total', 20, 4)->notNull()->default(0.0000);
            $table->decimal('deductions_total', 20, 4)->notNull()->default(0.0000);
            $table->decimal('tax_withheld', 20, 4)->notNull()->default(0.0000);
            $table->decimal('insurance_premium', 20, 4)->notNull()->default(0.0000);
            
            // ستون محاسبه خودکار خالص پرداختی با رعایت دقت استاندارد NUMERIC(20,4)
            $table->decimal('net_payable', 20, 4)->storedAs('base_salary + allowances_total - deductions_total - tax_withheld - insurance_premium');
            
            $table->boolean('is_disbursed')->notNull()->default(false);
            $table->timestampTz('disbursed_at')->nullable();
            $table->uuid('journal_entry_id')->nullable(); // Logical Reference to Accounting Ledger

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        // ایجاد ایندکس‌های یکتا و بهینه‌سازی جستجوی لجرها
        DB::statement('CREATE UNIQUE INDEX uq_hr_payroll_period ON payroll_records (tenant_id, employee_id, fiscal_period_id) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_hr_payroll_ledger ON payroll_records(tenant_id, fiscal_period_id) WHERE deleted_at IS NULL;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_records');
    }
};