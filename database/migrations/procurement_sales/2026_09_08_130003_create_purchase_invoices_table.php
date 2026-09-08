<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * L6-PS-07 – Purchase Invoices (official AP documents).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->uuid('purchase_invoice_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->string('invoice_number', 100)->notNull();
            $table->uuid('supplier_id')->notNull();
            $table->uuid('purchase_order_id')->nullable();
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
            $table->string('supplier_invoice_ref', 100)->nullable();
            $table->text('description')->nullable();
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('CREATE UNIQUE INDEX uq_purchase_invoices_number ON purchase_invoices (tenant_id, invoice_number) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_purchase_invoices_supplier ON purchase_invoices (tenant_id, supplier_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_purchase_invoices_order ON purchase_invoices (tenant_id, purchase_order_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_purchase_invoices_status ON purchase_invoices (tenant_id, status) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};
