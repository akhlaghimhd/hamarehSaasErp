<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_orders', function (Blueprint $table) {
            $table->uuid('return_order_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // ارجاع منطقی به ماژول Tenant Management
            $table->string('return_number', 100)->notNull();
            $table->smallInteger('return_type')->notNull(); // 1: Sales Return (Customer Return), 2: Purchase Return (Supplier Return)
            $table->string('source_document_type', 100)->notNull(); // e.g. SALES_DELIVERY, PURCHASE_RECEIPT
            $table->uuid('source_document_id')->notNull(); // Logical Reference to Source Document
            $table->uuid('business_partner_id')->notNull(); // Logical Reference to Business Partner
            $table->timestampTz('return_date')->notNull()->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->smallInteger('status')->notNull()->default(1); // 1: Pending, 2: Approved, 3: Completed, 0: Rejected
            $table->text('reason')->nullable();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            // کلید خارجی فیزیکی به tenants حذف شد.
        });

        DB::statement('CREATE UNIQUE INDEX uq_return_orders_number ON return_orders(tenant_id, return_number) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_return_orders_source_ref ON return_orders (tenant_id, source_document_type, source_document_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('return_orders');
    }
};