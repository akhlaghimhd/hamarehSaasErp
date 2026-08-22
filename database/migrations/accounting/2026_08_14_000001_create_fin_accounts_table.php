<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ۱. ابتدا ساختاربندی کامل جدول و کلید اصلی
        Schema::create('fin_accounts', function (Blueprint $table) {
            $table->uuid('account_id')->primary();
            $table->uuid('tenant_id');
            
            // فقط تعریف فیلد (بدون ایجاد کلید خارجی در این مرحله)
            $table->uuid('parent_account_id')->nullable();
            
            $table->string('code', 50);
            $table->string('name', 200);
            $table->smallInteger('account_type'); // 1: Asset, 2: Liability, 3: Equity, 4: Revenue, 5: Expense
            $table->smallInteger('level')->default(1);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            // Audit Fields
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            // Indexes
            $table->unique(['tenant_id', 'code'], 'uq_fin_accounts_code');
            $table->index('parent_account_id', 'idx_fin_accounts_parent');
            $table->index(['tenant_id', 'account_type'], 'idx_fin_accounts_type');
        });

        // ۲. اضافه کردن کلید خارجی خود-ارجاعی پس از اتمام ساخت جدول
        Schema::table('fin_accounts', function (Blueprint $table) {
            $table->foreign('parent_account_id')
                  ->references('account_id')->on('fin_accounts')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_accounts');
    }
};