<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_user_roles', function (Blueprint $table) {
            $table->uuid('admin_user_role_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('admin_user_id')->notNull();
            $table->uuid('admin_role_id')->notNull();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            $table->foreign('admin_user_id')->references('admin_user_id')->on('admin_users')->onDelete('restrict');
            $table->foreign('admin_role_id')->references('admin_role_id')->on('admin_roles')->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX uq_admin_user_roles ON admin_user_roles(admin_user_id, admin_role_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_roles');
    }
};