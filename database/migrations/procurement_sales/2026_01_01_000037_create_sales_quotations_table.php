<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_quotations', function (Blueprint $table) {
            $table->uuid('quotation_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // ارجاع منطقی به ماژول Tenant Management
            $table->string('quotation_number', 100)->notNull();
            $table->uuid('customer_id')->notNull(); // Logical Reference to Business Partner (Customer)
            $table->date('quotation_date')->notNull()->default(DB::raw('CURRENT_DATE'));
            $table->date('valid_until')->notNull();
            $table->decimal('subtotal_amount', 20, 4)->notNull()->default(0.0000);
            $table->decimal('tax_amount', 20, 4)->notNull()->default(0.0000);
            $table->decimal('discount_amount', 20, 4)->notNull()->default(0.0000);
            $table->decimal('total_amount', 20, 4)->notNull()->default(0.0000);
            $table->smallInteger('status')->notNull()->default(1); // 1: Draft, 2: Sent, 3: Accepted, 4: Expired, 0: Rejected
            $table->uuid('currency_id')->notNull(); // Logical Reference to Master Data (Currency)

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            // کلید خارجی فیزیکی به tenants (خارج از ماژول) حذف شد.
        });

        DB::statement('CREATE UNIQUE INDEX uq_sales_quotations_number ON sales_quotations(tenant_id, quotation_number) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_sales_quotations_customer ON sales_quotations(tenant_id, customer_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_quotations');
    }
};