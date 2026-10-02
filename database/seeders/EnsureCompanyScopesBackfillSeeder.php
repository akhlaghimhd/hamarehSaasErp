<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ensures COMPANY scopes exist for each erp_companies row and backfills
 * primary-company scope onto members that have no COMPANY scope yet —
 * so holding company filter on members list is testable.
 */
class EnsureCompanyScopesBackfillSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('erp_companies') || !Schema::hasTable('tenant_scopes')) {
            $this->command?->warn('erp_companies or tenant_scopes missing — skip.');

            return;
        }

        $tenantIds = DB::table('tenants')->pluck('tenant_id')->map(fn ($id) => (string) $id)->all();
        foreach ($tenantIds as $tenantId) {
            DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);
            $created = $this->ensureCompanyScopes($tenantId);
            $linked = $this->backfillPrimaryCompanyOnMembers($tenantId);
            $this->command?->info("Tenant {$tenantId}: company_scopes+={$created} members_linked={$linked}");
        }
    }

    private function ensureCompanyScopes(string $tenantId): int
    {
        $companies = DB::table('erp_companies')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->get(['company_id', 'name', 'legal_name', 'is_primary']);

        $created = 0;
        foreach ($companies as $c) {
            $exists = DB::table('tenant_scopes')
                ->where('tenant_id', $tenantId)
                ->whereRaw('UPPER(scope_type) = ?', ['COMPANY'])
                ->where('reference_id', $c->company_id)
                ->whereNull('deleted_at')
                ->exists();
            if ($exists) {
                continue;
            }
            $label = $c->name ?: $c->legal_name ?: 'Company';
            DB::table('tenant_scopes')->insert([
                'scope_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'scope_name' => $label,
                'scope_type' => 'COMPANY',
                'reference_id' => $c->company_id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created++;
        }

        return $created;
    }

    private function backfillPrimaryCompanyOnMembers(string $tenantId): int
    {
        if (!Schema::hasTable('tenant_user_scopes') || !Schema::hasTable('tenant_users')) {
            return 0;
        }

        $primaryCompanyId = DB::table('erp_companies')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderByDesc('is_primary')
            ->value('company_id');
        if (!$primaryCompanyId) {
            return 0;
        }

        $scopeId = DB::table('tenant_scopes')
            ->where('tenant_id', $tenantId)
            ->whereRaw('UPPER(scope_type) = ?', ['COMPANY'])
            ->where('reference_id', $primaryCompanyId)
            ->whereNull('deleted_at')
            ->value('scope_id');
        if (!$scopeId) {
            return 0;
        }

        $members = DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->get(['tenant_user_id', 'user_id']);

        $linked = 0;
        foreach ($members as $m) {
            $hasCompany = DB::table('tenant_user_scopes')
                ->join('tenant_scopes', 'tenant_user_scopes.scope_id', '=', 'tenant_scopes.scope_id')
                ->where('tenant_user_scopes.tenant_user_id', $m->tenant_user_id)
                ->whereNull('tenant_user_scopes.deleted_at')
                ->whereNull('tenant_scopes.deleted_at')
                ->whereRaw('UPPER(tenant_scopes.scope_type) = ?', ['COMPANY'])
                ->exists();
            if ($hasCompany) {
                continue;
            }
            DB::table('tenant_user_scopes')->insert([
                'assignment_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'tenant_user_id' => $m->tenant_user_id,
                'scope_id' => $scopeId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $linked++;
        }

        return $linked;
    }
}
