<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_delivery_orders', function (Blueprint $table) {
            $table->uuid('delivery_order_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // ارجاع منطقی به ماژول Tenant Management
            $table->string('delivery_number', 100)->notNull();
            $table->uuid('id_sales_order_source')->notNull(); // Logical Reference to Sales Order
            $table->uuid('customer_id')->notNull(); // Logical Reference to Business Partner
            $table->timestampTz('shipping_date')->notNull()->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->smallInteger('status')->notNull()->default(1); // 1: Prepared, 2: Dispatched, 3: Delivered, 0: Cancelled
            $table->text('shipping_address')->nullable();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            // کلید خارجی فیزیکی به tenants حذف شد.
        });

        DB::statement('CREATE UNIQUE INDEX uq_sales_deliveries_number ON sales_delivery_orders(tenant_id, delivery_number) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_sal_deliveries_source_order ON sales_delivery_orders (tenant_id, id_sales_order_source, customer_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_delivery_orders');
    }
};