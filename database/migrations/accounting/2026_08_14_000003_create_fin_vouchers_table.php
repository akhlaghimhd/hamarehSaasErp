<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_vouchers', function (Blueprint $table) {
            $table->uuid('voucher_id')->primary();
            $table->uuid('tenant_id');
            
            $table->date('voucher_date');
            $table->text('description');
            $table->decimal('total_amount', 20, 4)->default(0); // دقت بالای مالی
            $table->string('reference_number', 100);
            $table->uuid('currency_id')->nullable(); // Logical Ref به MasterData
            $table->smallInteger('status')->default(1); // 1: Draft, 2: Posted, 3: Cancelled

            // Audit Fields
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->unique(['tenant_id', 'reference_number'], 'uq_fin_vouchers_ref');
            $table->index(['tenant_id', 'voucher_date'], 'idx_fin_vouchers_date');
            $table->index('status', 'idx_fin_vouchers_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_vouchers');
    }
};