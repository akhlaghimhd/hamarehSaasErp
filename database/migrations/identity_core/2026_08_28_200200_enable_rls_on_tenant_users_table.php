<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F1 extension – RLS on tenant_users
 * Pattern identical to tenant_roles (Isolation §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tenant_users ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_users FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_users');

        DB::statement("
            CREATE POLICY tenant_isolation_policy ON tenant_users
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_users');
        DB::statement('ALTER TABLE tenant_users NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_users DISABLE ROW LEVEL SECURITY');
    }
};