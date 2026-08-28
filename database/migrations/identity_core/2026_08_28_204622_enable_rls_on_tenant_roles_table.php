<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B1 – Fix RLS on tenant_roles (core table for Identity)
 *
 * According to:
 * - Tenant Isolation Architecture Standard §4 (Database)
 * - ERP SaaS Core Identity Database Design v1.0 §12
 *
 * This is the first table where RLS was enabled.
 * Policy must be safe (no uuid cast error on empty context).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Enable Row Level Security
        DB::statement('ALTER TABLE tenant_roles ENABLE ROW LEVEL SECURITY');

        // 2. Force RLS even for table owner / superuser
        DB::statement('ALTER TABLE tenant_roles FORCE ROW LEVEL SECURITY');

        // 3. Drop existing policy (if rerun)
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_roles');

        // 4. Policy with nullif for safe empty context
        //    Prevents "invalid input syntax for type uuid" when app.current_tenant_id is empty
        DB::statement("
            CREATE POLICY tenant_isolation_policy ON tenant_roles
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
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_roles');
        DB::statement('ALTER TABLE tenant_roles NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_roles DISABLE ROW LEVEL SECURITY');
    }
};