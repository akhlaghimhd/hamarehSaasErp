<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_voucher_items', function (Blueprint $table) {
            $table->uuid('item_id')->primary();
            $table->uuid('tenant_id');
            
            // FK فیزیکی درون ماژول مجاز است
            $table->foreignUuid('voucher_id')->references('voucher_id')->on('fin_vouchers')->onDelete('cascade');
            $table->foreignUuid('account_id')->references('account_id')->on('fin_accounts')->onDelete('restrict');
            
            // Logical References (بدون قید فیزیکی برای حفظ مرزبندی)
            $table->uuid('cost_center_id')->nullable(); 
            $table->uuid('business_partner_id')->nullable(); 
            
            $table->text('description');
            $table->decimal('debit', 20, 4)->default(0);
            $table->decimal('credit', 20, 4)->default(0);

            // Audit Fields
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index('voucher_id', 'idx_fin_voucher_items_voucher');
            $table->index('account_id', 'idx_fin_voucher_items_account');
            $table->index(['tenant_id', 'business_partner_id'], 'idx_fin_voucher_items_bp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_voucher_items');
    }
};