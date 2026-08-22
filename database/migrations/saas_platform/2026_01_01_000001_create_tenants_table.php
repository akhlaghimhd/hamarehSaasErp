<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('tenant_id')->primary();
            
            // Core SaaS Fields based on Layer 1 Architecture
            $table->string('tenant_code', 100);
            $table->string('tenant_name', 200);
            $table->string('legal_name', 300)->nullable();
            $table->smallInteger('tenant_type')->default(1);
            $table->string('slug', 100);
            $table->boolean('primary_domain_enabled')->default(false);
            $table->smallInteger('domain_status')->default(1);
            $table->smallInteger('status')->default(1);
            
            // Standard Timestamp & Auditing Fields
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            // Architectural Indexes
            $table->unique('tenant_code', 'uq_tenants_code');
            $table->unique('slug', 'uq_tenants_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};