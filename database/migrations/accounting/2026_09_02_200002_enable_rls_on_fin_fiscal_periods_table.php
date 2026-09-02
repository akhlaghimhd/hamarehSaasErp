<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L6-ACC-01 – RLS on fin_fiscal_periods
 * Pattern identical to erp_companies / tenant_users (Tenant Isolation Architecture Standard §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE fin_fiscal_periods ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fin_fiscal_periods FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_fiscal_periods');

        DB::statement("
            CREATE POLICY tenant_isolation_policy ON fin_fiscal_periods
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_fiscal_periods');
        DB::statement('ALTER TABLE fin_fiscal_periods NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fin_fiscal_periods DISABLE ROW LEVEL SECURITY');
    }
};
