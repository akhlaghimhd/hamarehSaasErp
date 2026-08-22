<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_scopes', function (Blueprint $table) {
            $table->uuid('scope_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            
            // نام محدوده (مثلاً: شعبه تهران، انبار مرکزی)
            $table->string('scope_name', 150);
            
            // نوع محدوده (مثلاً: COMPANY, BRANCH, WAREHOUSE, DEPARTMENT)
            $table->string('scope_type', 50);
            
            // شناسه منطقی موجودیت در ماژول‌های دیگر (بدون کلید خارجی فیزیکی)
            $table->uuid('reference_id')->nullable();
            
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            // فیلدهای استاندارد Audit
            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            
            $table->bigInteger('row_version')->default(1);

            // ایندکس ترکیبی برای جلوگیری از ثبت Scope تکراری برای یک موجودیت خاص در یک مستأجر
            $table->unique(['tenant_id', 'scope_type', 'reference_id'], 'uq_tenant_scopes_reference')
                  ->whereNull('deleted_at');
                  
            $table->index(['tenant_id', 'scope_type'], 'idx_tenant_scopes_type')
                  ->whereNull('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_scopes');
    }
};
