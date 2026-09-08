<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_role_permissions', function (Blueprint $table) {
            $table->uuid('admin_role_permission_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('admin_role_id')->notNull();
            $table->uuid('admin_permission_id')->notNull();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            $table->foreign('admin_role_id')->references('admin_role_id')->on('admin_roles')->onDelete('restrict');
            $table->foreign('admin_permission_id')->references('admin_permission_id')->on('admin_permissions')->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_admin_role_permissions ON admin_role_permissions(admin_role_id, admin_permission_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_role_permissions');
    }
};
