<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F1 extension – RLS on tenant_user_roles (Identity Core)
 * Pattern identical to tenant_roles (Tenant Isolation Architecture Standard §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tenant_user_roles ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_user_roles FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_user_roles');

        DB::statement("
            CREATE POLICY tenant_isolation_policy ON tenant_user_roles
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_user_roles');
        DB::statement('ALTER TABLE tenant_user_roles NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_user_roles DISABLE ROW LEVEL SECURITY');
    }
};