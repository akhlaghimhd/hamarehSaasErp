<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user_scopes', function (Blueprint $table) {
            $table->uuid('assignment_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            
            // کاربری که دسترسی به او داده می‌شود
            $table->uuid('tenant_user_id');
            
            // محدوده‌ای که به کاربر تخصیص یافته است
            $table->uuid('scope_id');
            
            // فیلدهای استاندارد Audit
            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            
            $table->bigInteger('row_version')->default(1);

            // کلیدهای خارجی با محدودیت RESTRICT
            $table->foreign('tenant_user_id')
                  ->references('tenant_user_id')
                  ->on('tenant_users')
                  ->onDelete('restrict');

            $table->foreign('scope_id')
                  ->references('scope_id')
                  ->on('tenant_scopes')
                  ->onDelete('restrict');

            // جلوگیری از تخصیص تکراری یک محدوده به یک کاربر در یک مستأجر
            $table->unique(['tenant_id', 'tenant_user_id', 'scope_id'], 'uq_tenant_user_scopes')
                  ->whereNull('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_scopes');
    }
};
