<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_roles', function (Blueprint $table) {
            $table->uuid('admin_role_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('code', 50)->notNull();
            $table->string('name', 100)->notNull();
            $table->string('description', 500)->nullable();
            $table->smallInteger('status')->notNull()->default(1);

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('CREATE UNIQUE INDEX uq_admin_roles_code ON admin_roles(code) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_roles');
    }
};