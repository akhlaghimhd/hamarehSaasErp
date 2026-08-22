<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_acc_tax_transactions', function (Blueprint $table) {
            $table->uuid('transaction_id')->primary();
            $table->uuid('tenant_id');
            
            $table->date('transaction_date');
            $table->smallInteger('tax_type'); // 1: VAT, 2: Withholding Tax, 3: Income Tax
            
            $table->decimal('base_amount', 20, 4)->default(0);
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->decimal('tax_rate', 20, 4)->default(0);
            
            $table->string('reference_document_type', 100)->nullable(); // e.g., 'SALES_INVOICE'
            $table->uuid('reference_document_id')->nullable(); // Logical Reference
            $table->uuid('business_partner_id')->nullable(); // Logical Reference

            // Audit Fields
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'reference_document_type', 'reference_document_id'], 'idx_fin_tax_tx_ref');
            $table->index(['tenant_id', 'transaction_date'], 'idx_fin_tax_tx_date');
            $table->index('business_partner_id', 'idx_fin_tax_tx_bp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_acc_tax_transactions');
    }
};