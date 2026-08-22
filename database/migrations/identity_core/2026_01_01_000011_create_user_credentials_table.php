<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_credentials', function (Blueprint $table) {
            $table->uuid('credential_id')->primary();
            $table->uuid('user_id')->notNull();
            $table->string('password_hash', 500)->nullable();
            $table->smallInteger('authentication_type')->notNull()->default(1); // 1: Password, 2: OTP, 3: OAuth
            $table->boolean('is_verified')->notNull()->default(false);
            $table->boolean('two_factor_enabled')->notNull()->default(false);
            $table->integer('failed_login_count')->notNull()->default(0);
            $table->timestampTz('locked_until')->nullable();
            $table->timestampTz('last_password_change_at')->nullable();

            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('restrict');
        });

        DB::statement('CREATE INDEX idx_user_credentials_user ON user_credentials(user_id) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_credentials');
    }
};