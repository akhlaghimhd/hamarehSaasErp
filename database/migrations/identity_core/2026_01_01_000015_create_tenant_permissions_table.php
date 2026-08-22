<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_permissions', function (Blueprint $table) {
            $table->uuid('tenant_permission_id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 150);
            $table->string('name', 200);
            $table->string('module_name', 100)->nullable();
            $table->string('action_type', 50)->nullable();
            $table->string('description', 500)->nullable();
            $table->smallInteger('status')->default(1);
            
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            
            $table->bigInteger('row_version')->default(1);

            $table->unique(['tenant_id', 'code'], 'uq_tenant_permissions_code');
            $table->index(['tenant_id', 'module_name'], 'idx_tenant_permissions_module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_permissions');
    }
};