<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F1 – Enable real PostgreSQL Row Level Security on tenant_roles
 *
 * According to:
 * - Tenant Isolation Architecture Standard §4 (Database)
 * - ERP SaaS Core Identity Database Design v1.0 §12
 *
 * Pattern:
 * - ENABLE + FORCE ROW LEVEL SECURITY
 * - Policy uses nullif so empty/missing app.current_tenant_id never casts to uuid
 * - Application DB role must be NOSUPERUSER and NOBYPASSRLS
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tenant_roles ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_roles FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_roles');

        DB::statement("
            CREATE POLICY tenant_isolation_policy ON tenant_roles
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_roles');
        DB::statement('ALTER TABLE tenant_roles NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_roles DISABLE ROW LEVEL SECURITY');
    }
};