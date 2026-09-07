<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L6-HR-01 – RLS on HR operational tables.
 */
return new class extends Migration
{
    private array $tables = [
        'employees',
        'employee_profiles',
        'attendance_records',
        'payroll_records',
        'hr_documents',
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
