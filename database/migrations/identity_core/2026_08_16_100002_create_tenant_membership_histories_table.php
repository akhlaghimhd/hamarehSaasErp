<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_membership_histories', function (Blueprint $table) {
            $table->uuid('history_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            
            // ارتباط با ردیف عضویت کاربر در مستأجر
            $table->uuid('tenant_user_id');
            
            // ثبت وضعیت‌های تغییر یافته
            $table->smallInteger('previous_status')->nullable();
            $table->smallInteger('new_status');
            
            // دلیل یا توضیحات تغییر وضعیت (مثلاً "خروج از شرکت" یا "تعلیق به دلیل نقض قوانین")
            $table->string('reason_code', 50)->nullable();
            $table->text('description')->nullable();
            
            // تاریخ اعمال تغییر
            $table->timestampTz('effective_date')->default(DB::raw('NOW()'));

            // فیلدهای استاندارد Audit
            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            
            $table->bigInteger('row_version')->default(1);

            // کلید خارجی قفل شده روی عضویت اصلی
            $table->foreign('tenant_user_id')
                  ->references('tenant_user_id')
                  ->on('tenant_users')
                  ->onDelete('restrict');

            // ایندکس برای جستجوی سریع تاریخچه یک کاربر خاص در یک مستأجر
            $table->index(['tenant_id', 'tenant_user_id'], 'idx_tenant_membership_histories_user')
                  ->whereNull('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_membership_histories');
    }
};
