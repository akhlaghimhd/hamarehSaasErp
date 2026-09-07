<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L6-MFG-01 – RLS on all manufacturing operational tables.
 * Pattern identical to inv_* / fin_* (Tenant Isolation Architecture Standard).
 */
return new class extends Migration
{
    private array $tables = [
        'mfg_work_centers',
        'mfg_boms',
        'mfg_bom_items',
        'mfg_production_orders',
        'mfg_production_routing',
        'mfg_production_logs',
        'mfg_quality_inspections',
        'mfg_material_consumptions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON {$table}
                FOR ALL
                USING (
                    tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
                )
                WITH CHECK (
                    tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
                )
            ");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }

    private function tableExists(string $table): bool
    {
        $row = DB::selectOne(
            'SELECT to_regclass(?) AS reg',
            ['public.' . $table]
        );

        return $row && $row->reg !== null;
    }
};
