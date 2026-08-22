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
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->uuid('profile_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // ایندکس برای تفکیک مستأجر (بدون کلید خارجی فیزیکی به tenants)
            $table->uuid('employee_id')->index(); // ارجاع منطقی به جدول employees (بدون FK بین‌جدولی سخت)
            $table->string('national_code', 20)->nullable();
            $table->string('father_name', 100)->nullable();
            $table->smallInteger('gender')->nullable(); // 1: Male, 2: Female
            $table->smallInteger('marital_status')->nullable(); // 1: Single, 2: Married
            $table->date('birth_date')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_phone', 50)->nullable();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        // ایجاد ایندکس یکتا برای پروفایل هر کارمند به تفکیک مستأجر و وضعیت حذف نرم (Soft Delete)
        DB::statement('CREATE UNIQUE INDEX uq_employee_profiles_employee ON employee_profiles(tenant_id, employee_id) WHERE deleted_at IS NULL;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};