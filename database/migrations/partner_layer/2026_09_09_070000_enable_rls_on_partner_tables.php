<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L3-P3-02 – Enable RLS on PartnerLayer tables that carry tenant_id.
 *
 * Tables with tenant_id:
 *   - partners (nullable — platform-level partners allowed)
 *   - partner_tenant_assignments
 *   - partner_commissions
 *
 * Other PartnerLayer tables isolate via partner_id (parent) and do not
 * receive independent tenant RLS in this migration.
 *
 * Policy (nullable-aware):
 *   tenant_id IS NULL OR tenant_id = current app.current_tenant_id
 */
return new class extends Migration
{
    private array $tables = [
        'partners',
        'partner_tenant_assignments',
        'partner_commissions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            $row = DB::selectOne('SELECT to_regclass(?) AS reg', ['public.' . $table]);
            if (!$row || $row->reg === null) {
                continue;
            }

            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");

            DB::statement("
                CREATE POLICY tenant_isolation_policy ON {$table}
                FOR ALL
                USING (
                    tenant_id IS NULL
                    OR tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
                )
                WITH CHECK (
                    tenant_id IS NULL
                    OR tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
                )
            ");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            $row = DB::selectOne('SELECT to_regclass(?) AS reg', ['public.' . $table]);
            if (!$row || $row->reg === null) {
                continue;
            }
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
