<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L2-M01 – RLS on support_tickets (tenant-scoped table)
 * Pattern identical to inventory / accounting RLS migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE support_tickets ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE support_tickets FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON support_tickets');

        DB::statement("
            CREATE POLICY tenant_isolation_policy ON support_tickets
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON support_tickets');
        DB::statement('ALTER TABLE support_tickets NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE support_tickets DISABLE ROW LEVEL SECURITY');
    }
};
