<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->uuid('attachment_id')->primary();
            $table->uuid('tenant_id');
            
            // Polymorphic Logical Reference (بدون FK فیزیکی)
            $table->string('target_entity_type', 100); // مثلاً: 'sales_orders', 'employees', 'items'
            $table->uuid('target_entity_id');
            
            // File Metadata
            $table->string('file_name', 255);
            $table->string('file_path', 500); // مسیر در Storage (S3, Local)
            $table->string('mime_type', 100);
            $table->integer('file_size_bytes');
            $table->string('file_hash', 128)->nullable(); // برای جلوگیری از فایل تکراری و امنیت
            
            // Audit Fields
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);
        });

        // ایندکس ترکیبی قدرتمند دقیقاً طبق سند پرفورمنس معماری
        DB::statement('CREATE INDEX idx_attachments_entity_type ON attachments (tenant_id, target_entity_type, target_entity_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};