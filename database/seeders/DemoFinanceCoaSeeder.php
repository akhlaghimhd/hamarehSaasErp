<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * FIN-P0-18 — Minimal Iranian-style CoA + leading ledger + open period control for demo tenant.
 *
 * Coding law: child code = parent code + sequential digit (1,2,3…).
 * Example: 1 → 11,12 → 111,112 ; 4 → 41 → 411
 *
 * Idempotent. Does not invent fin_fiscal_periods (calendar owned outside Finance BC);
 * uses a stable demo period UUID for local QA.
 *
 * Usage:
 *   docker compose exec app php artisan db:seed --class=FinancePermissionSeeder
 *   docker compose exec app php artisan db:seed --class=DemoFinanceCoaSeeder
 *   docker compose exec app php artisan db:seed --class=ResetDemoFinanceCoaSeeder
 */
class DemoFinanceCoaSeeder extends Seeder
{
    private const DEMO_TENANT_ID = '3ab77cac-1343-4b13-8e14-0d887aad132a';

    /** Stable demo fiscal period id for QA (logical → future calendar). */
    public const DEMO_PERIOD_ID = 'a1000000-0000-4000-8000-000000000001';

    public function run(): void
    {
        if (! Schema::hasTable('fin_acc_accounts') || ! Schema::hasTable('fin_acc_ledgers')) {
            $this->command?->error('Finance tables missing — run financial_accounting migrations first.');

            return;
        }

        $tenantId = $this->resolveTenantId();
        if ($tenantId === null) {
            $this->command?->error('Demo tenant not found.');

            return;
        }

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

        $companyId = $this->resolvePrimaryCompanyId($tenantId);
        if ($companyId === null) {
            $this->command?->error('Primary company missing — run DemoTenantOwnerSeeder first.');

            return;
        }

        $ledgerId = $this->ensureLeadingLedger($tenantId, $companyId);
        $accountCount = $this->ensureCoa($tenantId);
        $this->ensurePeriodControl($tenantId, $companyId);
        $permLinked = $this->linkFinancePermsToTenantAdmin($tenantId);

        $this->command?->info('Demo Finance CoA ready:');
        $this->command?->info('  tenant_id:   '.$tenantId);
        $this->command?->info('  company_id:  '.$companyId);
        $this->command?->info('  ledger_id:   '.$ledgerId);
        $this->command?->info('  period_id:   '.self::DEMO_PERIOD_ID);
        $this->command?->info('  accounts:    '.$accountCount);
        $this->command?->info('  finance perms on tenant-admin: '.$permLinked);
    }

    private function resolveTenantId(): ?string
    {
        $fromEnv = env('DEMO_TENANT_ID');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return DB::table('tenants')->where('tenant_id', $fromEnv)->exists() ? $fromEnv : null;
        }

        if (DB::table('tenants')->where('tenant_id', self::DEMO_TENANT_ID)->exists()) {
            return self::DEMO_TENANT_ID;
        }

        return DB::table('tenants')->orderBy('created_at')->value('tenant_id');
    }

    private function resolvePrimaryCompanyId(string $tenantId): ?string
    {
        if (! Schema::hasTable('erp_companies')) {
            return null;
        }

        $q = DB::table('erp_companies')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        if (Schema::hasColumn('erp_companies', 'is_primary')) {
            $id = (clone $q)->where('is_primary', true)->value('company_id');
            if ($id) {
                return (string) $id;
            }
        }

        $id = $q->orderBy('created_at')->value('company_id');

        return $id ? (string) $id : null;
    }

    private function ensureLeadingLedger(string $tenantId, string $companyId): string
    {
        $existing = DB::table('fin_acc_ledgers')
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('code', 'LG')
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return (string) $existing->ledger_id;
        }

        $ledgerId = (string) Str::uuid();
        DB::table('fin_acc_ledgers')->insert([
            'ledger_id'   => $ledgerId,
            'tenant_id'   => $tenantId,
            'company_id'  => $companyId,
            'code'        => 'LG',
            'name'        => 'دفتر کل اصلی',
            'is_leading'  => true,
            'status'      => 1,
            'row_version' => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return $ledgerId;
    }

    /**
     * Hierarchical codes: parent + seq (1→11→111). account_type: 1A 2L 3E 4R 5X
     */
    private function ensureCoa(string $tenantId): int
    {
        $tree = [
            // Assets — 1
            ['code' => '1', 'name' => 'دارایی‌ها', 'type' => 1, 'balance' => 1, 'postable' => false, 'level' => 1, 'children' => [
                ['code' => '11', 'name' => 'دارایی‌های جاری', 'type' => 1, 'balance' => 1, 'postable' => false, 'level' => 2, 'children' => [
                    ['code' => '111', 'name' => 'صندوق', 'type' => 1, 'balance' => 1, 'postable' => true, 'level' => 3],
                    ['code' => '112', 'name' => 'بانک', 'type' => 1, 'balance' => 1, 'postable' => true, 'level' => 3],
                    ['code' => '113', 'name' => 'حساب‌های دریافتنی', 'type' => 1, 'balance' => 1, 'postable' => true, 'level' => 3],
                ]],
                ['code' => '12', 'name' => 'دارایی‌های غیرجاری', 'type' => 1, 'balance' => 1, 'postable' => false, 'level' => 2, 'children' => [
                    ['code' => '121', 'name' => 'دارایی ثابت مشهود', 'type' => 1, 'balance' => 1, 'postable' => true, 'level' => 3],
                    ['code' => '122', 'name' => 'استهلاک انباشته', 'type' => 1, 'balance' => 2, 'postable' => true, 'level' => 3],
                ]],
            ]],
            // Liabilities — 2
            ['code' => '2', 'name' => 'بدهی‌ها', 'type' => 2, 'balance' => 2, 'postable' => false, 'level' => 1, 'children' => [
                ['code' => '21', 'name' => 'بدهی‌های جاری', 'type' => 2, 'balance' => 2, 'postable' => false, 'level' => 2, 'children' => [
                    ['code' => '211', 'name' => 'حساب‌های پرداختنی', 'type' => 2, 'balance' => 2, 'postable' => true, 'level' => 3],
                    ['code' => '212', 'name' => 'مالیات بر ارزش افزوده پرداختنی', 'type' => 2, 'balance' => 2, 'postable' => true, 'level' => 3],
                ]],
            ]],
            // Equity — 3
            ['code' => '3', 'name' => 'حقوق صاحبان سهام', 'type' => 3, 'balance' => 2, 'postable' => false, 'level' => 1, 'children' => [
                ['code' => '31', 'name' => 'سرمایه', 'type' => 3, 'balance' => 2, 'postable' => true, 'level' => 2],
                ['code' => '32', 'name' => 'سود (زیان) انباشته', 'type' => 3, 'balance' => 2, 'postable' => true, 'level' => 2],
            ]],
            // Revenue — 4
            ['code' => '4', 'name' => 'درآمدها', 'type' => 4, 'balance' => 2, 'postable' => false, 'level' => 1, 'children' => [
                ['code' => '41', 'name' => 'فروش کالا و خدمات', 'type' => 4, 'balance' => 2, 'postable' => true, 'level' => 2],
            ]],
            // Expense — 5
            ['code' => '5', 'name' => 'هزینه‌ها', 'type' => 5, 'balance' => 1, 'postable' => false, 'level' => 1, 'children' => [
                ['code' => '51', 'name' => 'هزینه حقوق و دستمزد', 'type' => 5, 'balance' => 1, 'postable' => true, 'level' => 2],
                ['code' => '52', 'name' => 'هزینه اداری', 'type' => 5, 'balance' => 1, 'postable' => true, 'level' => 2],
            ]],
        ];

        $count = 0;
        foreach ($tree as $node) {
            $count += $this->upsertAccountTree($tenantId, $node, null);
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function upsertAccountTree(string $tenantId, array $node, ?string $parentId): int
    {
        $code = (string) $node['code'];
        $existing = DB::table('fin_acc_accounts')
            ->where('tenant_id', $tenantId)
            ->where('account_code', $code)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            $accountId = (string) $existing->account_id;
            $count = 0;
        } else {
            $accountId = (string) Str::uuid();
            DB::table('fin_acc_accounts')->insert([
                'account_id'         => $accountId,
                'tenant_id'          => $tenantId,
                'parent_account_id'  => $parentId,
                'account_code'       => $code,
                'name'               => (string) $node['name'],
                'account_type'       => (int) $node['type'],
                'account_level'      => (int) $node['level'],
                'normal_balance'     => (int) $node['balance'],
                'is_control_account' => ! (bool) $node['postable'],
                'is_postable'        => (bool) $node['postable'],
                'status'             => 1,
                'row_version'        => 1,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
            $count = 1;
        }

        foreach ($node['children'] ?? [] as $child) {
            $count += $this->upsertAccountTree($tenantId, $child, $accountId);
        }

        return $count;
    }

    private function ensurePeriodControl(string $tenantId, string $companyId): void
    {
        if (! Schema::hasTable('fin_acc_period_controls')) {
            return;
        }

        $exists = DB::table('fin_acc_period_controls')
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('period_id', self::DEMO_PERIOD_ID)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('fin_acc_period_controls')->insert([
            'period_control_id' => (string) Str::uuid(),
            'tenant_id'         => $tenantId,
            'company_id'        => $companyId,
            'period_id'         => self::DEMO_PERIOD_ID,
            'control_status'    => 'OPEN',
            'row_version'       => 1,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    private function linkFinancePermsToTenantAdmin(string $tenantId): int
    {
        if (! Schema::hasTable('tenant_role_permissions')) {
            return 0;
        }

        $roleId = DB::table('tenant_roles')
            ->where('tenant_id', $tenantId)
            ->where('code', 'tenant-admin')
            ->whereNull('deleted_at')
            ->value('tenant_role_id');

        if (! $roleId) {
            return 0;
        }

        $permIds = DB::table('tenant_permissions')
            ->where('tenant_id', $tenantId)
            ->where('code', 'like', 'finance.%')
            ->whereNull('deleted_at')
            ->pluck('tenant_permission_id');

        $linked = 0;
        foreach ($permIds as $permId) {
            $exists = DB::table('tenant_role_permissions')
                ->where('tenant_id', $tenantId)
                ->where('tenant_role_id', $roleId)
                ->where('tenant_permission_id', $permId)
                ->exists();

            if ($exists) {
                continue;
            }

            $row = [
                'tenant_id'            => $tenantId,
                'tenant_role_id'       => $roleId,
                'tenant_permission_id' => $permId,
                'created_at'           => now(),
                'updated_at'           => now(),
            ];

            if (Schema::hasColumn('tenant_role_permissions', 'tenant_role_permission_id')) {
                $row['tenant_role_permission_id'] = (string) Str::uuid();
            }
            if (Schema::hasColumn('tenant_role_permissions', 'row_version')) {
                $row['row_version'] = 1;
            }

            DB::table('tenant_role_permissions')->insert($row);
            $linked++;
        }

        return $linked;
    }
}
