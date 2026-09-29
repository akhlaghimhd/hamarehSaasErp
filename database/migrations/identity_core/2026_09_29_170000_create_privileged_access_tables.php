<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * ID-W2-02 — Privileged / Emergency (break-glass) access.
 * Time-boxed elevation grants for roles marked is_privileged.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Mark roles that may only be held via privileged grants (or permanent for owner setup).
        if (Schema::hasTable('tenant_roles') && !Schema::hasColumn('tenant_roles', 'is_privileged')) {
            Schema::table('tenant_roles', function (Blueprint $table) {
                $table->boolean('is_privileged')->default(false)->after('status');
            });
        }

        Schema::create('tenant_privileged_grants', function (Blueprint $table) {
            $table->uuid('grant_id')->primary();
            $table->uuid('tenant_id')->notNull();
            $table->uuid('user_id')->notNull();
            $table->uuid('tenant_role_id')->notNull();
            $table->string('reason', 500)->notNull();
            // PENDING | ACTIVE | REVOKED | EXPIRED | DENIED
            $table->string('status', 20)->notNull()->default('PENDING');
            $table->unsignedInteger('duration_minutes')->notNull()->default(60);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->uuid('requested_by')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->uuid('revoked_by')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->string('revoke_reason', 500)->nullable();
            $table->timestampsTz();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->unsignedBigInteger('row_version')->default(1);

            $table->foreign('tenant_role_id')
                ->references('tenant_role_id')
                ->on('tenant_roles')
                ->onDelete('restrict');
        });

        DB::statement('CREATE INDEX idx_tenant_privileged_grants_user ON tenant_privileged_grants(tenant_id, user_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_tenant_privileged_grants_status ON tenant_privileged_grants(tenant_id, status) WHERE deleted_at IS NULL');

        DB::statement('ALTER TABLE tenant_privileged_grants ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_privileged_grants FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_privileged_grants');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON tenant_privileged_grants
            FOR ALL
            USING (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
            WITH CHECK (tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_privileged_grants');
        Schema::dropIfExists('tenant_privileged_grants');

        if (Schema::hasTable('tenant_roles') && Schema::hasColumn('tenant_roles', 'is_privileged')) {
            Schema::table('tenant_roles', function (Blueprint $table) {
                $table->dropColumn('is_privileged');
            });
        }
    }
};
