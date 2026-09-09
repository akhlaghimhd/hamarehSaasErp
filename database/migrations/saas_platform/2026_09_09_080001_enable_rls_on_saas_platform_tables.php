<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L1-01 – Enable RLS on SaaS Platform (Layer 1) tables that carry tenant_id.
 *
 * Tables with tenant_id (strict isolation):
 *   - tenant_domains
 *   - tenant_settings
 *   - tenant_status_history
 *   - subscriptions
 *   - tenant_wallets
 *   - invoice_profiles
 *   - platform_invoices
 *   - coupon_usages
 *
 * Platform-master tables (plans, plan_versions, addons, coupons, etc.) have no
 * tenant_id and are intentionally excluded from tenant RLS.
 *
 * tenants table itself is the root identity and is not filtered by tenant_id.
 *
 * Policy pattern matches ProcurementSales / PartnerLayer / Inventory:
 *   tenant_id = current_setting('app.current_tenant_id')::uuid
 */
return new class extends Migration
{
    private array $tables = [
        'tenant_domains',
        'tenant_settings',
        'tenant_status_history',
        'subscriptions',
        'tenant_wallets',
        'invoice_profiles',
        'platform_invoices',
        'coupon_usages',
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
