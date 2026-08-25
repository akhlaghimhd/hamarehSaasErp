<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_invoices', function (Blueprint $table) {
            $table->uuid('invoice_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->notNull();
            $table->uuid('invoice_profile_id')->nullable();
            $table->string('invoice_number', 100)->notNull();
            $table->decimal('total_amount', 20, 4)->notNull();
            $table->decimal('discount_amount', 20, 4)->notNull()->default(0);
            $table->decimal('tax_amount', 20, 4)->notNull()->default(0);
            $table->decimal('final_amount', 20, 4)->notNull();
            $table->smallInteger('status')->notNull()->default(1);
            $table->timestampTz('issue_date')->notNull()->default(DB::raw('NOW()'));
            $table->timestampTz('due_date')->nullable();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->notNull()->default(1);

            $table->foreign('tenant_id')
                  ->references('tenant_id')
                  ->on('tenants')
                  ->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_platform_invoices_number ON platform_invoices(invoice_number) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_platform_invoices_tenant ON platform_invoices(tenant_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_invoices');
    }
};