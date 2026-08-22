<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_users', function (Blueprint $table) {
            $table->uuid('tenant_user_id')->primary();
            $table->uuid('tenant_id')->notNull()->index(); // ارجاع منطقی به ماژول SaaS Platform
            $table->uuid('user_id')->notNull(); // درون‌ماژولی (Identity Core)
            $table->uuid('employee_id')->nullable(); // ارجاع منطقی به HR module
            $table->boolean('is_owner')->notNull()->default(false);
            $table->smallInteger('status')->notNull()->default(1);

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            // کلید خارجی فیزیکی به tenants حذف شد. فقط کلید درون‌ماژولی باقی می‌ماند:
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_tenant_users ON tenant_users(tenant_id, user_id) WHERE deleted_at IS NULL;');
        DB::statement('CREATE INDEX idx_tenant_users_employee ON tenant_users(tenant_id, employee_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_users');
    }
};