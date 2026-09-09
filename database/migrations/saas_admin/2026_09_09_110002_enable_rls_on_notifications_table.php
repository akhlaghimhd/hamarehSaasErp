<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L2-M01 – RLS on notifications (tenant-scoped table)
 * Pattern identical to inventory / accounting RLS migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE notifications ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE notifications FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON notifications');

        DB::statement("
            CREATE POLICY tenant_isolation_policy ON notifications
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON notifications');
        DB::statement('ALTER TABLE notifications NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE notifications DISABLE ROW LEVEL SECURITY');
    }
};
