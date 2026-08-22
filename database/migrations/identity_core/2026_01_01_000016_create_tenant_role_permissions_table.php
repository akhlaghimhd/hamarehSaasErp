<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_role_permissions', function (Blueprint $table) {
            $table->uuid('tenant_role_permission_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('tenant_role_id');
            $table->uuid('tenant_permission_id');
            
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->bigInteger('row_version')->default(1);

            $table->foreign('tenant_role_id')->references('tenant_role_id')->on('tenant_roles')->onDelete('restrict');
            $table->foreign('tenant_permission_id')->references('tenant_permission_id')->on('tenant_permissions')->onDelete('restrict');
            
            $table->unique(['tenant_id', 'tenant_role_id', 'tenant_permission_id'], 'uq_tenant_role_permissions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_role_permissions');
    }
};