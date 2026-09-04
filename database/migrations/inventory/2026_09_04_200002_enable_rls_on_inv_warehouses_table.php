<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L6-INV-08 – RLS on inv_warehouses
 * Pattern identical to fin_accounts (Tenant Isolation Architecture Standard §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE inv_warehouses ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE inv_warehouses FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON inv_warehouses');

        DB::statement("
            CREATE POLICY tenant_isolation_policy ON inv_warehouses
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON inv_warehouses');
        DB::statement('ALTER TABLE inv_warehouses NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE inv_warehouses DISABLE ROW LEVEL SECURITY');
    }
};
