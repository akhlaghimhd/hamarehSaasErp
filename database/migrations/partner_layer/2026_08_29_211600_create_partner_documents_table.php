<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * P3-D9 — partner_documents (Database Layer 3 SSOT).
 * partner_id: physical FK within PartnerLayer.
 * verified_by: logical reference to admin user (no cross-module FK).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_documents', function (Blueprint $table) {
            $table->uuid('partner_document_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('partner_id');
            $table->string('document_type', 100);
            $table->string('document_number', 100)->nullable();
            $table->string('storage_path', 1000);
            $table->smallInteger('status')->default(1); // 1 Pending, 2 Approved, 3 Rejected
            $table->timestampTz('verified_at')->nullable();
            $table->uuid('verified_by')->nullable(); // Logical reference

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->foreign('partner_id')
                ->references('partner_id')
                ->on('partners')
                ->onDelete('restrict');
        });

        DB::statement('CREATE INDEX idx_partner_docs_parent ON partner_documents (partner_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_documents');
    }
};
