<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F1 extension – RLS on erp_companies (Organization)
 * Pattern identical to tenant_roles / erp_branches (Tenant Isolation Architecture Standard §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE erp_companies ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE erp_companies FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON erp_companies');

        DB::statement("
            CREATE POLICY tenant_isolation_policy ON erp_companies
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON erp_companies');
        DB::statement('ALTER TABLE erp_companies NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE erp_companies DISABLE ROW LEVEL SECURITY');
    }
};