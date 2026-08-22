<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user_roles', function (Blueprint $table) {
            $table->uuid('tenant_user_role_id')->primary();
            $table->uuid('tenant_id')->notNull()->index(); // ارجاع منطقی به ماژول SaaS Platform
            $table->uuid('user_id')->notNull(); // درون‌ماژولی (Identity Core)
            $table->uuid('tenant_role_id')->notNull(); // درون‌ماژولی (Identity Core)

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            // کلید خارجی فیزیکی به tenants حذف شد. فقط کلیدهای درون‌ماژولی باقی می‌مانند:
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('restrict');
            $table->foreign('tenant_role_id')->references('tenant_role_id')->on('tenant_roles')->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_tenant_user_roles ON tenant_user_roles(tenant_id, user_id, tenant_role_id) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_tenant_user_roles_user ON tenant_user_roles(tenant_id, user_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_roles');
    }
};