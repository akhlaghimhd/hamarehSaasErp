<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F1 – Enable real PostgreSQL Row Level Security on tenant_roles
 *
 * According to:
 * - Tenant Isolation Architecture Standard §4 (Database)
 * - ERP SaaS Core Identity Database Design v1.0 §12
 *
 * This is the standard pattern for all tenant_id tables.
 * Application-level Global Scope remains; RLS is the second mandatory layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Enable Row Level Security
        DB::statement('ALTER TABLE tenant_roles ENABLE ROW LEVEL SECURITY');

        // 2. Force RLS even for table owner / superuser roles used by the application
        //    (prevents accidental bypass when connection role has elevated privileges)
        DB::statement('ALTER TABLE tenant_roles FORCE ROW LEVEL SECURITY');

        // 3. Create the isolation policy
        //    current_setting(..., true) returns NULL if the setting is missing → policy fails safely
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON tenant_roles
            FOR ALL
            USING (
                tenant_id = current_setting('app.current_tenant_id', true)::uuid
            )
            WITH CHECK (
                tenant_id = current_setting('app.current_tenant_id', true)::uuid
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_roles');
        DB::statement('ALTER TABLE tenant_roles NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_roles DISABLE ROW LEVEL SECURITY');
    }
};