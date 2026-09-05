<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L6-PS-00 – Enable RLS on all Procurement & Sales tables.
 * Pattern identical to Inventory (Tenant Isolation Architecture Standard).
 */
return new class extends Migration
{
    private array $tables = [
        'purchase_requisitions',
        'purchase_orders',
        'purchase_order_items',
        'purchase_receipts',
        'purchase_receipt_items',
        'sales_quotations',
        'sales_quotation_items',
        'sales_orders',
        'sales_order_items',
        'sales_delivery_orders',
        'sales_delivery_order_items',
        'return_orders',
        'return_order_items',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
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
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
