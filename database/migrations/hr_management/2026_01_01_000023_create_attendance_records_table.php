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
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->uuid('attendance_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // ایندکس برای تفکیک مستأجر (بدون کلید خارجی فیزیکی به tenants)
            $table->uuid('employee_id')->index(); // ارجاع منطقی به جدول employees (بدون کلید خارجی سخت)
            $table->date('attendance_date')->notNull();
            $table->timestampTz('clock_in')->nullable();
            $table->timestampTz('clock_out')->nullable();
            $table->decimal('overtime_hours', 20, 4)->notNull()->default(0.0000);
            $table->decimal('delay_hours', 20, 4)->notNull()->default(0.0000);
            $table->smallInteger('status')->notNull()->default(1); // 1: Present, 2: Absent, 3: Leave, 4: Mission

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        // ایجاد ایندکس‌های بهینه‌سازی و یکتایی بر اساس استانداردها
        DB::statement('CREATE UNIQUE INDEX uq_attendance_employee_date ON attendance_records(tenant_id, employee_id, attendance_date) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_attendance_date_lookup ON attendance_records(tenant_id, attendance_date DESC) WHERE deleted_at IS NULL;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};