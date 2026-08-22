<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_companies', function (Blueprint $table) {
            $table->uuid('company_id')->primary();
            $table->uuid('tenant_id');
            
            $table->string('code', 50);
            $table->string('name', 200);
            $table->string('registration_number', 100)->nullable();
            $table->string('economic_code', 100)->nullable(); // کد اقتصادی/مالیاتی
            $table->boolean('is_active')->default(true);

            // Audit Fields
            $table->timestampTz('created_at')->useCurrent();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);
        });

        // Unique index for code within a tenant (Partial Index for Soft Deletes)
        DB::statement('CREATE UNIQUE INDEX uq_erp_companies_code ON erp_companies(tenant_id, code) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_companies');
    }
};