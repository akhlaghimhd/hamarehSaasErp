<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->uuid('purchase_receipt_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // ارجاع منطقی به ماژول Tenant Management
            $table->string('receipt_number', 100)->notNull();
            $table->uuid('id_purchase_order_source')->notNull(); // Logical Reference to Purchase Order
            $table->uuid('supplier_id')->notNull(); // Logical Reference to Business Partner
            $table->timestampTz('receipt_date')->notNull()->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->smallInteger('status')->notNull()->default(1); // 1: Draft, 2: Verified, 3: Posted, 0: Cancelled
            $table->text('notes')->nullable();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            // کلید خارجی فیزیکی به tenants (خارج از ماژول) حذف شد.
        });

        DB::statement('CREATE UNIQUE INDEX uq_purchase_receipts_number ON purchase_receipts(tenant_id, receipt_number) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_pur_receipts_source_order ON purchase_receipts(tenant_id, id_purchase_order_source, supplier_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receipts');
    }
};