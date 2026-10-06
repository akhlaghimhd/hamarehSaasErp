<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_user_sessions')) {
            return;
        }

        Schema::create('admin_user_sessions', function (Blueprint $table) {
            $table->uuid('session_id')->primary();
            $table->uuid('admin_user_id');
            $table->string('token_hash', 256);
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('expires_at');
            $table->timestampTz('last_activity_at')->useCurrent();
        });

        DB::statement('CREATE INDEX idx_admin_sessions_user ON admin_user_sessions (admin_user_id) WHERE is_active = TRUE');
        DB::statement('CREATE INDEX idx_admin_sessions_token ON admin_user_sessions (token_hash) WHERE is_active = TRUE');
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_sessions');
    }
};
