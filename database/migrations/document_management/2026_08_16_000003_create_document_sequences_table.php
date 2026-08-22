<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->uuid('sequence_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id'); // ایزوله‌سازی چندمستأجری
            
            $table->uuid('company_id')->nullable();
            $table->string('module_code', 50); // e.g., FIN, INV, PUR, SAL, HR, MFG
            $table->string('document_type', 100); // e.g., SALES_INVOICE, PURCHASE_ORDER
            
            $table->smallInteger('document_scope')->default(1); // 1: Tenant, 2: Company, 3: Branch, 4: Module
            $table->string('owner_type', 50)->nullable(); // e.g., COMPANY, BRANCH, MODULE
            $table->uuid('owner_id')->nullable();
            
            $table->string('prefix', 20)->nullable();
            $table->string('suffix', 20)->nullable();
            $table->integer('padding_length')->default(6);
            $table->bigInteger('current_value')->default(0);
            $table->smallInteger('reset_period')->default(1); // 1: Never, 2: Yearly, 3: Monthly
            $table->timestampTz('last_reset_at')->nullable();
            
            $table->boolean('is_active')->default(true);

            // Audit Fields
            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->bigInteger('row_version')->default(1);
        });

        // ایندکس‌های تخصصی و قدرتمند تعریف شده در معماری
        DB::statement("CREATE UNIQUE INDEX uq_document_sequences_scope ON document_sequences(tenant_id, module_code, document_type, document_scope, COALESCE(company_id, '00000000-0000-0000-0000-000000000000'::UUID)) WHERE is_active = TRUE;");
        DB::statement("CREATE INDEX idx_document_sequences_module ON document_sequences(tenant_id, module_code) WHERE is_active = TRUE;");
        DB::statement("CREATE INDEX idx_document_sequences_owner ON document_sequences(owner_id) WHERE is_active = TRUE;");
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};