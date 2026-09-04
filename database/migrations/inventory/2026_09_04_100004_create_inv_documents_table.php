<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * inv_documents — transactional inventory documents per Inventory_Logistics_Module.md
 * fiscal_period_id, business_partner_id, source_document_id are LOGICAL refs only (no physical FK).
 * document_type: 1 Receipt, 2 Issue, 3 Transfer, 4 Cycle Adjustment
 * status: 1 Draft, 2 Pending Approval, 3 Posted, 4 Voided
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inv_documents', function (Blueprint $table) {
            $table->uuid('document_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');

            // Logical references — no physical FK across modules (Rule 2.2)
            $table->uuid('fiscal_period_id');
            $table->smallInteger('document_type');
            $table->string('document_number', 100);
            $table->timestampTz('posting_date')->default(DB::raw('NOW()'));
            $table->string('source_document_type', 100)->nullable();
            $table->uuid('source_document_id')->nullable();
            $table->uuid('business_partner_id')->nullable();
            $table->smallInteger('status')->default(1);
            $table->string('description', 500)->nullable();

            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);
        });

        DB::statement('CREATE UNIQUE INDEX uq_inv_documents_number ON inv_documents(tenant_id, document_number) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_inv_documents_cross_lookup ON inv_documents(source_document_id, source_document_type);');
        DB::statement('CREATE INDEX idx_inv_documents_tenant ON inv_documents(tenant_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_documents');
    }
};
