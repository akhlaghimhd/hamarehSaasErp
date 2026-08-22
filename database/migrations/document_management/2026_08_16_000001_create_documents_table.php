<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('document_id')->primary();
            $table->uuid('tenant_id');
            
            $table->string('document_number', 100);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('document_type', 50); // e.g., CONTRACT, POLICY, MANUAL
            $table->smallInteger('status')->default(1); // 1: Draft, 2: Active, 3: Archived
            
            // Audit Fields
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);
        });

        DB::statement('CREATE UNIQUE INDEX uq_documents_number ON documents(tenant_id, document_number) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};