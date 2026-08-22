<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('purchase_order_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // ارجاع منطقی به ماژول Tenant Management
            $table->string('order_number', 100)->notNull();
            $table->uuid('supplier_id')->notNull(); // ارجاع منطقی به Master Data
            $table->date('order_date')->notNull()->default(DB::raw('CURRENT_DATE'));
            $table->date('delivery_date')->nullable();
            $table->decimal('subtotal_amount', 20, 4)->notNull()->default(0.0000);
            $table->decimal('tax_amount', 20, 4)->notNull()->default(0.0000);
            $table->decimal('discount_amount', 20, 4)->notNull()->default(0.0000);
            $table->decimal('total_amount', 20, 4)->notNull()->default(0.0000);
            $table->smallInteger('status')->notNull()->default(1); // 1: Draft, 2: Sent, 3: Partially Received, 4: Completed, 0: Cancelled
            $table->uuid('currency_id')->notNull(); // ارجاع منطقی به Master Data

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            // کلید خارجی فیزیکی به tenants (خارج از ماژول) حذف شد.
        });

        DB::statement('CREATE UNIQUE INDEX uq_purchase_orders_number ON purchase_orders(tenant_id, order_number) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_purchase_orders_supplier ON purchase_orders(tenant_id, supplier_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};