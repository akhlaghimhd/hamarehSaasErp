<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_user_sessions', function (Blueprint $table) {
            $table->uuid('session_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('admin_user_id')->notNull();
            $table->string('token_hash', 256)->notNull();
            $table->string('ip_address', 45)->notNull();
            $table->string('user_agent', 500)->nullable();
            $table->boolean('is_active')->notNull()->default(true);
            $table->timestampTz('created_at')->notNull()->default(DB::raw('NOW()'));
            $table->timestampTz('expires_at')->notNull();
            $table->timestampTz('last_activity_at')->notNull()->default(DB::raw('NOW()'));
        });
        DB::statement('CREATE INDEX idx_admin_sessions_user ON admin_user_sessions(admin_user_id) WHERE is_active = TRUE;');

        Schema::create('admin_login_attempts', function (Blueprint $table) {
            $table->uuid('attempt_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('username', 150)->notNull();
            $table->string('ip_address', 45)->notNull();
            $table->string('user_agent', 500)->nullable();
            $table->boolean('is_successful')->notNull();
            $table->string('failure_reason', 100)->nullable();
            $table->timestampTz('attempted_at')->notNull()->default(DB::raw('NOW()'));
        });
        DB::statement('CREATE INDEX idx_admin_login_failures ON admin_login_attempts(ip_address, attempted_at) WHERE is_successful = FALSE;');

        Schema::create('admin_api_keys', function (Blueprint $table) {
            $table->uuid('api_key_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('admin_user_id')->notNull();
            $table->string('name', 100)->notNull();
            $table->string('key_prefix', 10)->notNull();
            $table->string('key_hash', 256)->notNull();
            $table->boolean('is_active')->notNull()->default(true);
            $table->timestampTz('created_at')->notNull()->default(DB::raw('NOW()'));
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });
        DB::statement('CREATE UNIQUE INDEX uq_admin_api_keys_hash ON admin_api_keys(key_hash) WHERE is_active = TRUE;');

        Schema::create('admin_webhooks', function (Blueprint $table) {
            $table->uuid('webhook_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name', 150)->notNull();
            $table->string('target_url', 1000)->notNull();
            $table->string('secret_token', 256)->nullable();
            $table->jsonb('event_types')->notNull();
            $table->boolean('is_active')->notNull()->default(true);
            $table->timestampTz('created_at')->notNull()->default(DB::raw('NOW()'));
            $table->timestampTz('updated_at')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_webhooks');
        Schema::dropIfExists('admin_api_keys');
        Schema::dropIfExists('admin_login_attempts');
        Schema::dropIfExists('admin_user_sessions');
    }
};
