<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L6-ACC-01 – RLS on fin_accounts
 * Pattern identical to erp_companies / tenant_users (Tenant Isolation Architecture Standard §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE fin_accounts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fin_accounts FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_accounts');

        DB::statement("
            CREATE POLICY tenant_isolation_policy ON fin_accounts
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_accounts');
        DB::statement('ALTER TABLE fin_accounts NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fin_accounts DISABLE ROW LEVEL SECURITY');
    }
};
