<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F1 extension – RLS on erp_branches (Organization)
 * Pattern identical to tenant_roles (Isolation §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE erp_branches ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE erp_branches FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON erp_branches');

        DB::statement("
            CREATE POLICY tenant_isolation_policy ON erp_branches
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON erp_branches');
        DB::statement('ALTER TABLE erp_branches NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE erp_branches DISABLE ROW LEVEL SECURITY');
    }
};