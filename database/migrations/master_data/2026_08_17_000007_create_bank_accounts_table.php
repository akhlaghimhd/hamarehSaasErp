<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->uuid('bank_account_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id'); // ایزوله‌سازی چند مستأجری
            
            $table->string('entity_type', 100); // Polymorphic Type (e.g., 'BUSINESS_PARTNER')
            $table->uuid('entity_id'); // ارجاع منطقی چندشکلی (بدون کلید خارجی فیزیکی)
            
            $table->string('bank_name', 100);
            $table->string('branch_name', 100)->nullable();
            $table->string('account_number', 100);
            $table->string('card_number', 50)->nullable();
            $table->string('iban', 100)->nullable(); // شماره شبا
            
            $table->boolean('is_primary')->default(false);
            $table->smallInteger('status')->default(1);
            
            // فیلدهای حسابرسی و همزمانی
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            // ایندکس‌ها
            $table->index(['entity_id', 'entity_type'], 'idx_bank_accounts_polymorphic');
            $table->index('tenant_id', 'idx_bank_accounts_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};