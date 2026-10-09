<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipe demo-tenant finance accounts (and dependent journal rows) then reseed CoA.
 *
 * Usage:
 *   docker compose exec app php artisan db:seed --class=ResetDemoFinanceCoaSeeder
 *
 * Wipe only (no reseed):
 *   RESET_COA_RESEED=0 docker compose exec app php artisan db:seed --class=ResetDemoFinanceCoaSeeder
 */
class ResetDemoFinanceCoaSeeder extends Seeder
{
    private const DEMO_TENANT_ID = '3ab77cac-1343-4b13-8e14-0d887aad132a';

    public function run(): void
    {
        if (! Schema::hasTable('fin_acc_accounts')) {
            $this->command?->error('fin_acc_accounts missing.');

            return;
        }

        $tenantId = $this->resolveTenantId();
        if ($tenantId === null) {
            $this->command?->error('Demo tenant not found.');

            return;
        }

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

        $this->wipeDependent($tenantId);

        $deleted = DB::table('fin_acc_accounts')->where('tenant_id', $tenantId)->delete();
        $this->command?->info("Wiped fin_acc_accounts rows: {$deleted} (tenant={$tenantId})");

        $remaining = DB::table('fin_acc_accounts')->where('tenant_id', $tenantId)->count();
        $this->command?->info("Remaining accounts: {$remaining}");

        $reseed = env('RESET_COA_RESEED', '1') !== '0';
        if ($reseed) {
            $this->call(DemoFinanceCoaSeeder::class);
            $after = DB::table('fin_acc_accounts')->where('tenant_id', $tenantId)->count();
            $this->command?->info("After reseed accounts: {$after}");
        } else {
            $this->command?->info('Reseed skipped (RESET_COA_RESEED=0).');
        }
    }

    private function resolveTenantId(): ?string
    {
        $fromEnv = env('DEMO_TENANT_ID');
        if (is_string($fromEnv) && $fromEnv !== ''
            && DB::table('tenants')->where('tenant_id', $fromEnv)->exists()) {
            return $fromEnv;
        }

        if (DB::table('tenants')->where('tenant_id', self::DEMO_TENANT_ID)->exists()) {
            return self::DEMO_TENANT_ID;
        }

        $id = DB::table('tenants')->orderBy('created_at')->value('tenant_id');

        return $id ? (string) $id : null;
    }

    private function wipeDependent(string $tenantId): void
    {
        $tables = [
            'fin_acc_journal_items',
            'fin_acc_journal_entries',
            'fin_acc_suggested_journal_lines',
            'fin_acc_suggested_journals',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'tenant_id')) {
                // lines may only have FK to parent journal
                if ($table === 'fin_acc_suggested_journal_lines' && Schema::hasTable('fin_acc_suggested_journals')) {
                    $ids = DB::table('fin_acc_suggested_journals')
                        ->where('tenant_id', $tenantId)
                        ->pluck('suggested_journal_id');
                    if ($ids->isNotEmpty()) {
                        DB::table($table)->whereIn('suggested_journal_id', $ids)->delete();
                    }
                }

                continue;
            }
            $n = DB::table($table)->where('tenant_id', $tenantId)->delete();
            $this->command?->info("Wiped {$table}: {$n}");
        }
    }
}
