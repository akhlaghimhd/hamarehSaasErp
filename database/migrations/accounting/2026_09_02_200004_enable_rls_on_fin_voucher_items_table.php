<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L6-ACC-01 – RLS on fin_voucher_items
 * Pattern identical to erp_companies / tenant_users (Tenant Isolation Architecture Standard §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE fin_voucher_items ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fin_voucher_items FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_voucher_items');

        DB::statement("
            CREATE POLICY tenant_isolation_policy ON fin_voucher_items
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON fin_voucher_items');
        DB::statement('ALTER TABLE fin_voucher_items NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fin_voucher_items DISABLE ROW LEVEL SECURITY');
    }
};
