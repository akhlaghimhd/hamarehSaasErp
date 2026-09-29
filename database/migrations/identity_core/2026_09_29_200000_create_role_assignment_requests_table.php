<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ID-W3-02 — Approval workflow for sensitive (or all gated) role assignments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_role_assignment_requests', function (Blueprint $table) {
            $table->uuid('request_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id'); // subject user receiving the role
            $table->uuid('tenant_role_id');
            $table->string('status', 20)->default('PENDING'); // PENDING|APPROVED|REJECTED|CANCELLED
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('valid_to')->nullable();
            $table->text('reason')->nullable();
            $table->uuid('requested_by');
            $table->uuid('reviewed_by')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            $table->index(['tenant_id', 'status'], 'idx_role_assign_req_status');
            $table->index(['tenant_id', 'user_id'], 'idx_role_assign_req_user');
        });

        DB::statement("ALTER TABLE tenant_role_assignment_requests ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE tenant_role_assignment_requests FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_role_assignment_requests");
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON tenant_role_assignment_requests
            FOR ALL
            USING (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
            WITH CHECK (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_role_assignment_requests');
        Schema::dropIfExists('tenant_role_assignment_requests');
    }
};
