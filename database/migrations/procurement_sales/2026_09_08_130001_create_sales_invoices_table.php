<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * L6-PS-07 – Sales Invoices (official AR documents).
 * Logical refs only: customer_id, sales_order_id, currency_id, fiscal_period_id, accounting_voucher_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->uuid('sales_invoice_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->string('invoice_number', 100)->notNull();
            $table->uuid('customer_id')->notNull();
            $table->uuid('sales_order_id')->nullable();
            $table->uuid('currency_id')->notNull();
            $table->uuid('fiscal_period_id')->nullable();
            $table->date('invoice_date')->notNull()->default(DB::raw('CURRENT_DATE'));
            $table->date('due_date')->nullable();
            $table->date('posting_date')->nullable();
            $table->decimal('subtotal_amount', 20, 4)->notNull()->default(0.0000);
            $table->decimal('discount_amount', 20, 4)->notNull()->default(0.0000);
            $table->decimal('tax_amount', 20, 4)->notNull()->default(0.0000);
            $table->decimal('total_amount', 20, 4)->notNull()->default(0.0000);
            $table->smallInteger('status')->notNull()->default(1);
            $table->uuid('accounting_voucher_id')->nullable();
            $table->string('tax_invoice_number', 100)->nullable();
            $table->text('description')->nullable();
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('CREATE UNIQUE INDEX uq_sales_invoices_number ON sales_invoices (tenant_id, invoice_number) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_sales_invoices_customer ON sales_invoices (tenant_id, customer_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_sales_invoices_order ON sales_invoices (tenant_id, sales_order_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_sales_invoices_status ON sales_invoices (tenant_id, status) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoices');
    }
};
