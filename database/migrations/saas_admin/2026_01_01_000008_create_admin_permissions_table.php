<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_permissions', function (Blueprint $table) {
            $table->uuid('admin_permission_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('code', 100)->notNull();
            $table->string('name', 150)->notNull();
            $table->string('module_name', 100)->nullable();
            $table->string('permission_group', 100)->nullable();
            $table->string('action_type', 50)->nullable(); // C, R, U, D, EXECUTE
            $table->string('description', 500)->nullable();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('CREATE UNIQUE INDEX uq_admin_permissions_code ON admin_permissions(code) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_permissions');
    }
};
