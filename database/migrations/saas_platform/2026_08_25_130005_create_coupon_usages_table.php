<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->uuid('coupon_usage_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('coupon_id')->notNull();
            $table->uuid('tenant_id')->notNull();
            $table->uuid('invoice_id')->nullable();
            $table->decimal('discount_amount', 20, 4)->notNull();
            $table->timestampTz('used_at')->notNull()->default(DB::raw('NOW()'));

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->notNull()->default(1);

            $table->foreign('coupon_id')
                  ->references('coupon_id')
                  ->on('coupons')
                  ->onDelete('restrict');

            $table->foreign('tenant_id')
                  ->references('tenant_id')
                  ->on('tenants')
                  ->onDelete('restrict');

            $table->foreign('invoice_id')
                  ->references('invoice_id')
                  ->on('platform_invoices')
                  ->onDelete('set null');
        });

        DB::statement('CREATE INDEX idx_coupon_usages_tenant ON coupon_usages(tenant_id) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_coupon_usages_coupon ON coupon_usages(coupon_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};