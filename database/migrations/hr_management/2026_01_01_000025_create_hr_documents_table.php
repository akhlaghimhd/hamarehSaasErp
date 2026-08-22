<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hr_documents', function (Blueprint $table) {
            $table->uuid('hr_document_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index(); // ایندکس برای تفکیک مستأجر (بدون کلید خارجی فیزیکی به tenants)
            $table->uuid('employee_id')->index(); // ارجاع منطقی به جدول employees (بدون FK سخت)
            $table->string('document_type_code', 100)->notNull(); // e.g. 'CONTRACT', 'NDA', 'BACKGROUND_CHECK'
            $table->string('document_title', 200)->notNull();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->uuid('attachment_id')->nullable(); // Logical Reference to Central Attachments Framework
            $table->smallInteger('status')->notNull()->default(1); // 1: Valid, 2: Expired, 3: Terminated

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        // ایجاد ایندکس‌های بهینه‌سازی جستجوی اسناد به تفکیک مستأجر و کارمند
        DB::statement('CREATE INDEX idx_hr_documents_employee ON hr_documents(tenant_id, employee_id) WHERE deleted_at IS NULL;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_documents');
    }
};