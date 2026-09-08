<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('audit_log_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->nullable();
            $table->uuid('user_id')->nullable(); // Logical ref to Identity user
            $table->uuid('admin_user_id')->nullable(); // Logical ref to SaaS Admin user
            $table->uuid('session_id')->nullable();
            $table->uuid('request_id')->nullable();
            $table->string('entity_name', 100)->notNull();
            $table->uuid('entity_id')->nullable();
            $table->string('action_type', 50)->notNull(); // CREATE, UPDATE, DELETE, LOGIN, LOGOUT, APPROVE, REJECT, EXPORT
            $table->smallInteger('severity')->notNull()->default(1); // 1 Info, 2 Warning, 3 Critical
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->jsonb('details')->nullable();
            $table->timestampTz('log_date')->notNull()->default(DB::raw('NOW()'));
            $table->timestampTz('created_at')->notNull()->default(DB::raw('NOW()'));
            $table->uuid('created_by')->nullable();
        });

        DB::statement('CREATE INDEX idx_audit_logs_tenant ON audit_logs(tenant_id);');
        DB::statement('CREATE INDEX idx_audit_logs_entity ON audit_logs(entity_name, entity_id);');
        DB::statement('CREATE INDEX idx_audit_logs_user ON audit_logs(user_id);');
        DB::statement('CREATE INDEX idx_audit_logs_date ON audit_logs(log_date DESC);');
        DB::statement('CREATE INDEX idx_audit_logs_request ON audit_logs(request_id);');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
