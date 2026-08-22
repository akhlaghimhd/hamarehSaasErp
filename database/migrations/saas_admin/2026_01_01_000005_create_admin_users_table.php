<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_users', function (Blueprint $table) {
            $table->uuid('admin_user_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('username', 100)->notNull();
            $table->string('email', 200)->notNull();
            $table->text('password_hash')->notNull();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->smallInteger('status')->notNull()->default(1);
            $table->timestampTz('last_login_at')->nullable();
            $table->integer('failed_login_count')->notNull()->default(0);
            $table->timestampTz('locked_until')->nullable();
            $table->boolean('two_factor_enabled')->notNull()->default(false);

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });

        DB::statement('CREATE UNIQUE INDEX uq_admin_users_username ON admin_users(username) WHERE deleted_at IS NULL;');
        DB::statement('CREATE UNIQUE INDEX uq_admin_users_email ON admin_users(email) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};