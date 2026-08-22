<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->uuid('version_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            
            // بر اساس معماری، ارتباط فیزیکی (FK) درون یک Bounded Context مجاز است
            $table->foreignUuid('document_id')->references('document_id')->on('documents')->onDelete('cascade');
            
            $table->integer('version_number');
            $table->uuid('attachment_id')->nullable(); // ارجاع منطقی/فیزیکی به جدول attachments
            $table->text('change_summary')->nullable(); // خلاصه تغییرات این نسخه
            
            // Audit Fields
            $table->timestampTz('created_at')->default(DB::raw('NOW()'));
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);
        });

        // جلوگیری از ایجاد دو نسخه هم‌شماره برای یک سند
        DB::statement("CREATE UNIQUE INDEX uq_document_versions_number ON document_versions(tenant_id, document_id, version_number) WHERE deleted_at IS NULL;");
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};