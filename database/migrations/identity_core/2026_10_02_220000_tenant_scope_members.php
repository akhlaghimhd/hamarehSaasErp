<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Multi-reference scopes (same type only).
 * tenant_scopes.reference_id remains the primary/first ref for backward compatibility.
 * Members hold the full set of same-type reference_ids for a named scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_scope_members')) {
            Schema::create('tenant_scope_members', function (Blueprint $table) {
                $table->uuid('scope_member_id')->primary()->default(DB::raw('gen_random_uuid()'));
                $table->uuid('tenant_id');
                $table->uuid('scope_id');
                $table->uuid('reference_id');

                $table->timestampTz('created_at')->default(DB::raw('NOW()'));
                $table->uuid('created_by')->nullable();
                $table->timestampTz('updated_at')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->softDeletesTz();
                $table->uuid('deleted_by')->nullable();
                $table->bigInteger('row_version')->default(1);

                $table->unique(['scope_id', 'reference_id'], 'uq_tenant_scope_members_scope_ref');
                $table->index(['tenant_id', 'scope_id'], 'idx_tenant_scope_members_scope');
                $table->index(['tenant_id', 'reference_id'], 'idx_tenant_scope_members_ref');
            });
        }

        DB::statement('ALTER TABLE tenant_scopes DROP CONSTRAINT IF EXISTS uq_tenant_scopes_reference');

        DB::statement("
            INSERT INTO tenant_scope_members (scope_member_id, tenant_id, scope_id, reference_id, created_at, row_version)
            SELECT gen_random_uuid(), s.tenant_id, s.scope_id, s.reference_id, COALESCE(s.created_at, NOW()), 1
            FROM tenant_scopes s
            WHERE s.reference_id IS NOT NULL
              AND s.deleted_at IS NULL
              AND NOT EXISTS (
                SELECT 1 FROM tenant_scope_members m
                WHERE m.scope_id = s.scope_id
                  AND m.reference_id = s.reference_id
                  AND m.deleted_at IS NULL
              )
        ");

        DB::statement('ALTER TABLE tenant_scope_members ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_scope_members FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_scope_members');
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON tenant_scope_members
            FOR ALL
            USING (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
            WITH CHECK (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_scope_members');
        Schema::dropIfExists('tenant_scope_members');
    }
};
