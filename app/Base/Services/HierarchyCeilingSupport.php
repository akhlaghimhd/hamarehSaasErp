<?php

declare(strict_types=1);

namespace App\Base\Services;

use Illuminate\Support\Facades\DB;

/**
 * ADR-ID-ORG-005 — hierarchical company/branch ceiling helpers.
 * Used by HoldingAccessService for grant/list bounds (self + descendants, no peers/parents).
 */
final class HierarchyCeilingSupport
{
    /**
     * @param  list<string>  $rootCompanyIds
     * @return list<string>
     */
    public function companySubtreeIds(string $tenantId, array $rootCompanyIds): array
    {
        $roots = array_values(array_unique(array_filter(array_map('strval', $rootCompanyIds))));
        if ($roots === []) {
            return [];
        }

        $all = DB::table('erp_companies')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->get(['company_id', 'parent_company_id']);

        $children = [];
        foreach ($all as $row) {
            $pid = $row->parent_company_id !== null ? (string) $row->parent_company_id : '';
            if ($pid === '') {
                continue;
            }
            $children[$pid][] = (string) $row->company_id;
        }

        $out = [];
        $queue = $roots;
        $seen = [];
        while ($queue !== []) {
            $id = array_shift($queue);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $id;
            foreach ($children[$id] ?? [] as $child) {
                $queue[] = $child;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public function allActiveCompanyIds(string $tenantId): array
    {
        return DB::table('erp_companies')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->pluck('company_id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * @return list<string>
     */
    public function allActiveBranchIds(string $tenantId): array
    {
        return DB::table('erp_branches')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->pluck('branch_id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * @param  list<string>  $branchIds
     * @return list<string>
     */
    public function companyIdsForBranches(string $tenantId, array $branchIds): array
    {
        if ($branchIds === []) {
            return [];
        }

        return DB::table('erp_branches')
            ->where('tenant_id', $tenantId)
            ->whereIn('branch_id', $branchIds)
            ->whereNull('deleted_at')
            ->pluck('company_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $ceilingCompanies
     * @param  list<string>  $actorBranchIds
     * @return list<string>
     */
    public function branchesInCeiling(string $tenantId, array $ceilingCompanies, array $actorBranchIds): array
    {
        if ($ceilingCompanies === []) {
            return [];
        }

        if ($actorBranchIds !== []) {
            return DB::table('erp_branches')
                ->where('tenant_id', $tenantId)
                ->whereIn('branch_id', $actorBranchIds)
                ->whereIn('company_id', $ceilingCompanies)
                ->whereNull('deleted_at')
                ->pluck('branch_id')
                ->map(fn ($id) => (string) $id)
                ->all();
        }

        return DB::table('erp_branches')
            ->where('tenant_id', $tenantId)
            ->whereIn('company_id', $ceilingCompanies)
            ->whereNull('deleted_at')
            ->pluck('branch_id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }
}
