<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L6-ACC-01 – RLS on fin_vouchers
 * Pattern identical to erp_companies / tenant_users (Tenant Isolation Architecture Standard §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE fin_vouchers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fin_vouchers FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_vouchers');

        DB::statement("
            CREATE POLICY tenant_isolation_policy ON fin_vouchers
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_vouchers');
        DB::statement('ALTER TABLE fin_vouchers NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fin_vouchers DISABLE ROW LEVEL SECURITY');
    }
};
