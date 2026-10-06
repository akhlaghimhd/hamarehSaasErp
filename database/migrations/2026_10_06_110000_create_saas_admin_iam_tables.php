<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAASADM-P2 — Platform admin identity (Layer 2).
 * Separate from tenant IdentityCore users.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table) {
                $table->uuid('admin_user_id')->primary();
                $table->string('username', 100);
                $table->string('email', 200);
                $table->text('password_hash');
                $table->string('first_name', 100)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->string('mobile', 20)->nullable();
                $table->smallInteger('status')->default(1);
                $table->timestampTz('last_login_at')->nullable();
                $table->integer('failed_login_count')->default(0);
                $table->timestampTz('locked_until')->nullable();
                $table->boolean('two_factor_enabled')->default(false);
                $table->timestampTz('created_at')->useCurrent();
                $table->uuid('created_by')->nullable();
                $table->timestampTz('updated_at')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestampTz('deleted_at')->nullable();
                $table->uuid('deleted_by')->nullable();
                $table->bigInteger('row_version')->default(1);
            });

            DB::statement('CREATE UNIQUE INDEX uq_admin_users_username ON admin_users (username) WHERE deleted_at IS NULL');
            DB::statement('CREATE UNIQUE INDEX uq_admin_users_email ON admin_users (email) WHERE deleted_at IS NULL');
        }

        if (! Schema::hasTable('admin_roles')) {
            Schema::create('admin_roles', function (Blueprint $table) {
                $table->uuid('admin_role_id')->primary();
                $table->string('code', 50);
                $table->string('name', 100);
                $table->string('description', 500)->nullable();
                $table->smallInteger('status')->default(1);
                $table->timestampTz('created_at')->useCurrent();
                $table->uuid('created_by')->nullable();
                $table->timestampTz('updated_at')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestampTz('deleted_at')->nullable();
                $table->uuid('deleted_by')->nullable();
                $table->bigInteger('row_version')->default(1);
            });
            DB::statement('CREATE UNIQUE INDEX uq_admin_roles_code ON admin_roles (code) WHERE deleted_at IS NULL');
        }

        if (! Schema::hasTable('admin_permissions')) {
            Schema::create('admin_permissions', function (Blueprint $table) {
                $table->uuid('admin_permission_id')->primary();
                $table->string('code', 100);
                $table->string('name', 150);
                $table->string('module_name', 100)->nullable();
                $table->string('permission_group', 100)->nullable();
                $table->string('action_type', 50)->nullable();
                $table->string('description', 500)->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->uuid('created_by')->nullable();
                $table->timestampTz('updated_at')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestampTz('deleted_at')->nullable();
                $table->uuid('deleted_by')->nullable();
                $table->bigInteger('row_version')->default(1);
            });
            DB::statement('CREATE UNIQUE INDEX uq_admin_permissions_code ON admin_permissions (code) WHERE deleted_at IS NULL');
        }

        if (! Schema::hasTable('admin_user_roles')) {
            Schema::create('admin_user_roles', function (Blueprint $table) {
                $table->uuid('admin_user_role_id')->primary();
                $table->uuid('admin_user_id');
                $table->uuid('admin_role_id');
                $table->timestampTz('created_at')->useCurrent();
                $table->uuid('created_by')->nullable();
                $table->timestampTz('updated_at')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestampTz('deleted_at')->nullable();
                $table->uuid('deleted_by')->nullable();
                $table->bigInteger('row_version')->default(1);

                $table->foreign('admin_user_id')->references('admin_user_id')->on('admin_users')->cascadeOnDelete();
                $table->foreign('admin_role_id')->references('admin_role_id')->on('admin_roles')->cascadeOnDelete();
            });
            DB::statement('CREATE UNIQUE INDEX uq_admin_user_role ON admin_user_roles (admin_user_id, admin_role_id) WHERE deleted_at IS NULL');
        }

        if (! Schema::hasTable('admin_role_permissions')) {
            Schema::create('admin_role_permissions', function (Blueprint $table) {
                $table->uuid('admin_role_permission_id')->primary();
                $table->uuid('admin_role_id');
                $table->uuid('admin_permission_id');
                $table->timestampTz('created_at')->useCurrent();
                $table->uuid('created_by')->nullable();
                $table->timestampTz('updated_at')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestampTz('deleted_at')->nullable();
                $table->uuid('deleted_by')->nullable();
                $table->bigInteger('row_version')->default(1);

                $table->foreign('admin_role_id')->references('admin_role_id')->on('admin_roles')->cascadeOnDelete();
                $table->foreign('admin_permission_id')->references('admin_permission_id')->on('admin_permissions')->cascadeOnDelete();
            });
            DB::statement('CREATE UNIQUE INDEX uq_admin_role_permission ON admin_role_permissions (admin_role_id, admin_permission_id) WHERE deleted_at IS NULL');
        }

        if (! Schema::hasTable('admin_login_attempts')) {
            Schema::create('admin_login_attempts', function (Blueprint $table) {
                $table->uuid('attempt_id')->primary();
                $table->string('username', 150);
                $table->string('ip_address', 45);
                $table->string('user_agent', 500)->nullable();
                $table->boolean('is_successful');
                $table->string('failure_reason', 100)->nullable();
                $table->timestampTz('attempted_at')->useCurrent();
            });
            DB::statement('CREATE INDEX idx_admin_login_failures ON admin_login_attempts (ip_address, attempted_at) WHERE is_successful = FALSE');
        }

        if (! Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->uuid('system_setting_id')->primary();
                $table->string('setting_key', 150);
                $table->text('setting_value')->nullable();
                $table->string('description', 500)->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->uuid('created_by')->nullable();
                $table->timestampTz('updated_at')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestampTz('deleted_at')->nullable();
                $table->uuid('deleted_by')->nullable();
                $table->bigInteger('row_version')->default(1);
            });
            DB::statement('CREATE UNIQUE INDEX uq_system_settings_key ON system_settings (setting_key) WHERE deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_role_permissions');
        Schema::dropIfExists('admin_user_roles');
        Schema::dropIfExists('admin_login_attempts');
        Schema::dropIfExists('admin_permissions');
        Schema::dropIfExists('admin_roles');
        Schema::dropIfExists('admin_users');
        Schema::dropIfExists('system_settings');
    }
};
