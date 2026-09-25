<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\BusinessUnit;
use App\Modules\Organization\Models\BusinessUnitCompany;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\OrgHierarchy;
use App\Modules\Organization\Models\OrgHierarchyNode;
use App\Base\Context\TenantContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Smart Hierarchy Product Law v1.0 — P1 + P2(minimal) + P3 + P4
 *
 * System trees are derived from source entities. Never throw to CRUD callers
 * when used via safe(). Point-sync; idempotent upsert.
 *
 * P2 full feature-pack wiring is DEBT until SaaS Admin packs API exists.
 * P5 report contracts and P6 manual node origin column are DEBT.
 */
class HierarchySyncService
{
    public const CODE_LEGAL = 'SYS-LEGAL';
    public const CODE_ESTABLISHMENT = 'SYS-ESTABLISHMENT';
    public const CODE_PRODUCT = 'SYS-PRODUCT';

    public const STATUS_HEALTHY = 'healthy';
    public const STATUS_NEEDS_SYNC = 'needs_sync';
    public const STATUS_INCONSISTENT = 'inconsistent';

    public function syncCompany(Company $company): void
    {
        $tenantId = $this->resolveTenantId($company->tenant_id ?? null);
        if ($tenantId === '') {
            return;
        }

        $legalId = $this->ensureSystemHierarchy(
            $tenantId,
            self::CODE_LEGAL,
            'ساختار حقوقی',
            OrgHierarchy::PURPOSE_LEGAL
        );

        $parentNodeId = null;
        if (!empty($company->parent_company_id)) {
            $parentNodeId = $this->findNodeId(
                $tenantId,
                $legalId,
                'COMPANY',
                (string) $company->parent_company_id
            );
            if ($parentNodeId === null) {
                $parent = Company::withTrashed()
                    ->where('tenant_id', $tenantId)
                    ->where('company_id', $company->parent_company_id)
                    ->first();
                if ($parent) {
                    $parentNodeId = $this->upsertNode(
                        $tenantId,
                        $legalId,
                        'COMPANY',
                        (string) $parent->company_id,
                        null,
                        !$parent->trashed() && $parent->is_active !== false
                    );
                }
            }
        }

        $active = !$company->trashed() && $company->is_active !== false;
        $this->upsertNode(
            $tenantId,
            $legalId,
            'COMPANY',
            (string) $company->company_id,
            $parentNodeId,
            $active
        );

        $estId = $this->ensureSystemHierarchy(
            $tenantId,
            self::CODE_ESTABLISHMENT,
            'استقرار',
            OrgHierarchy::PURPOSE_ESTABLISHMENT
        );

        $this->upsertNode(
            $tenantId,
            $estId,
            'COMPANY',
            (string) $company->company_id,
            null,
            $active
        );
    }

    public function syncBranch(Branch $branch): void
    {
        $tenantId = $this->resolveTenantId($branch->tenant_id ?? null);
        if ($tenantId === '' || empty($branch->company_id)) {
            return;
        }

        $estId = $this->ensureSystemHierarchy(
            $tenantId,
            self::CODE_ESTABLISHMENT,
            'استقرار',
            OrgHierarchy::PURPOSE_ESTABLISHMENT
        );

        $companyNodeId = $this->findNodeId($tenantId, $estId, 'COMPANY', (string) $branch->company_id);
        if ($companyNodeId === null) {
            $co = Company::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $branch->company_id)
                ->first();
            if ($co) {
                $companyNodeId = $this->upsertNode(
                    $tenantId,
                    $estId,
                    'COMPANY',
                    (string) $co->company_id,
                    null,
                    !$co->trashed() && $co->is_active !== false
                );
            }
        }

        $active = !$branch->trashed() && $branch->is_active !== false;
        $this->upsertNode(
            $tenantId,
            $estId,
            'BRANCH',
            (string) $branch->branch_id,
            $companyNodeId,
            $active
        );
    }

    public function syncBranchesForCompany(string $companyId): void
    {
        $tenantId = $this->resolveTenantId(null);
        if ($tenantId === '') {
            return;
        }

        $branches = Branch::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->get();

        foreach ($branches as $branch) {
            $this->syncBranch($branch);
        }
    }

    /**
     * P4 — place BU under primary company in SYS-PRODUCT (CUSTOM purpose).
     * Only material when tenant has more than one active BU (product law threshold).
     */
    public function syncBusinessUnit(BusinessUnit $bu): void
    {
        $tenantId = $this->resolveTenantId($bu->tenant_id ?? null);
        if ($tenantId === '') {
            return;
        }

        $activeBuCount = BusinessUnit::where('tenant_id', $tenantId)->where('is_active', true)->count();
        if ($activeBuCount <= 1 && !$bu->trashed()) {
            // Keep single-BU tenants free of product-tree noise; still deactivate if leftover
            $this->deactivateEntityNodes('BUSINESS_UNIT', (string) $bu->business_unit_id);

            return;
        }

        $productId = $this->ensureSystemHierarchy(
            $tenantId,
            self::CODE_PRODUCT,
            'خطوط محصول',
            OrgHierarchy::PURPOSE_CUSTOM
        );

        $primaryCompanyId = BusinessUnitCompany::where('tenant_id', $tenantId)
            ->where('business_unit_id', $bu->business_unit_id)
            ->where('is_primary', true)
            ->value('company_id');

        if (!$primaryCompanyId) {
            $primaryCompanyId = BusinessUnitCompany::where('tenant_id', $tenantId)
                ->where('business_unit_id', $bu->business_unit_id)
                ->orderBy('created_at')
                ->value('company_id');
        }

        $parentNodeId = null;
        if ($primaryCompanyId) {
            $parentNodeId = $this->findNodeId($tenantId, $productId, 'COMPANY', (string) $primaryCompanyId);
            if ($parentNodeId === null) {
                $co = Company::withTrashed()
                    ->where('tenant_id', $tenantId)
                    ->where('company_id', $primaryCompanyId)
                    ->first();
                if ($co) {
                    $parentNodeId = $this->upsertNode(
                        $tenantId,
                        $productId,
                        'COMPANY',
                        (string) $co->company_id,
                        null,
                        !$co->trashed() && $co->is_active !== false
                    );
                }
            }
        }

        $active = !$bu->trashed() && $bu->is_active !== false;
        $this->upsertNode(
            $tenantId,
            $productId,
            'BUSINESS_UNIT',
            (string) $bu->business_unit_id,
            $parentNodeId,
            $active
        );
    }

    public function deactivateEntityNodes(string $entityType, string $entityId): void
    {
        $tenantId = $this->resolveTenantId(null);
        if ($tenantId === '') {
            return;
        }

        OrgHierarchyNode::where('tenant_id', $tenantId)
            ->where('entity_type', strtoupper($entityType))
            ->where('entity_id', $entityId)
            ->whereNull('deleted_at')
            ->update([
                'is_active'  => false,
                'updated_at' => now(),
            ]);
    }

    public function reactivateEntityNodes(string $entityType, string $entityId): void
    {
        $tenantId = $this->resolveTenantId(null);
        if ($tenantId === '') {
            return;
        }

        OrgHierarchyNode::where('tenant_id', $tenantId)
            ->where('entity_type', strtoupper($entityType))
            ->where('entity_id', $entityId)
            ->whereNull('deleted_at')
            ->update([
                'is_active'  => true,
                'updated_at' => now(),
            ]);
    }

    /**
     * P2 minimal — if structure is multi-entity, ensure system trees exist and are rebuilt.
     * Full pack upgrade/downgrade hooks are DEBT (no SaaS feature-pack API yet).
     *
     * @return array{tier: string, rebuilt: bool, company_count: int, branch_count: int, bu_count: int}
     */
    public function ensureStructuralTrees(?string $tenantId = null): array
    {
        $tenantId = $this->resolveTenantId($tenantId);
        if ($tenantId === '') {
            return [
                'tier' => 'unknown',
                'rebuilt' => false,
                'company_count' => 0,
                'branch_count' => 0,
                'bu_count' => 0,
            ];
        }

        $companyCount = Company::where('tenant_id', $tenantId)->count();
        $branchCount = Branch::where('tenant_id', $tenantId)->count();
        $buCount = BusinessUnit::where('tenant_id', $tenantId)->count();

        $tier = 'simple';
        if ($companyCount > 1 || $buCount > 1) {
            $tier = 'advanced';
        } elseif ($branchCount > 1 || $companyCount > 1) {
            $tier = 'standard';
        }

        $rebuilt = false;
        if ($companyCount >= 1) {
            // Always keep LEGAL/EST shells when any company exists (cheap ensure)
            $this->ensureSystemHierarchy($tenantId, self::CODE_LEGAL, 'ساختار حقوقی', OrgHierarchy::PURPOSE_LEGAL);
            $this->ensureSystemHierarchy($tenantId, self::CODE_ESTABLISHMENT, 'استقرار', OrgHierarchy::PURPOSE_ESTABLISHMENT);

            if ($tier !== 'simple') {
                $this->rebuildSystemTreesForTenant($tenantId);
                $rebuilt = true;
            } elseif ($companyCount === 1) {
                // Point-sync primary path without full scan noise for truly simple
                $co = Company::where('tenant_id', $tenantId)->orderBy('created_at')->first();
                if ($co) {
                    $this->syncCompany($co);
                    $this->syncBranchesForCompany($co->company_id);
                }
            }
        }

        if ($buCount > 1) {
            $this->ensureSystemHierarchy($tenantId, self::CODE_PRODUCT, 'خطوط محصول', OrgHierarchy::PURPOSE_CUSTOM);
            foreach (BusinessUnit::where('tenant_id', $tenantId)->get() as $bu) {
                $this->syncBusinessUnit($bu);
            }
        }

        return [
            'tier' => $tier,
            'rebuilt' => $rebuilt,
            'company_count' => $companyCount,
            'branch_count' => $branchCount,
            'bu_count' => $buCount,
        ];
    }

    /**
     * P3 — health report for support / admin.
     *
     * @return array{status: string, issues: list<array<string, mixed>>, counts: array<string, int>}
     */
    public function health(?string $tenantId = null): array
    {
        $tenantId = $this->resolveTenantId($tenantId);
        $issues = [];

        if ($tenantId === '') {
            return [
                'status' => self::STATUS_INCONSISTENT,
                'issues' => [['code' => 'no_tenant', 'message' => 'tenant context missing']],
                'counts' => [],
            ];
        }

        $companies = Company::where('tenant_id', $tenantId)->get();
        $branches = Branch::where('tenant_id', $tenantId)->get();
        $bus = BusinessUnit::where('tenant_id', $tenantId)->get();

        $legal = OrgHierarchy::where('tenant_id', $tenantId)->where('code', self::CODE_LEGAL)->first();
        $est = OrgHierarchy::where('tenant_id', $tenantId)->where('code', self::CODE_ESTABLISHMENT)->first();

        if ($companies->isNotEmpty() && !$legal) {
            $issues[] = ['code' => 'missing_legal_tree', 'message' => 'SYS-LEGAL hierarchy missing'];
        }
        if ($companies->isNotEmpty() && !$est) {
            $issues[] = ['code' => 'missing_establishment_tree', 'message' => 'SYS-ESTABLISHMENT hierarchy missing'];
        }

        if ($legal) {
            foreach ($companies as $co) {
                $exists = OrgHierarchyNode::where('tenant_id', $tenantId)
                    ->where('hierarchy_id', $legal->hierarchy_id)
                    ->where('entity_type', 'COMPANY')
                    ->where('entity_id', $co->company_id)
                    ->whereNull('deleted_at')
                    ->exists();
                if (!$exists) {
                    $issues[] = [
                        'code' => 'company_missing_legal_node',
                        'entity_id' => $co->company_id,
                        'message' => 'company not in LEGAL tree',
                    ];
                }
            }
        }

        if ($est) {
            foreach ($branches as $br) {
                $exists = OrgHierarchyNode::where('tenant_id', $tenantId)
                    ->where('hierarchy_id', $est->hierarchy_id)
                    ->where('entity_type', 'BRANCH')
                    ->where('entity_id', $br->branch_id)
                    ->whereNull('deleted_at')
                    ->exists();
                if (!$exists) {
                    $issues[] = [
                        'code' => 'branch_missing_establishment_node',
                        'entity_id' => $br->branch_id,
                        'message' => 'branch not in ESTABLISHMENT tree',
                    ];
                }
            }
        }

        // Orphan company nodes (entity hard-missing)
        $companyIds = $companies->pluck('company_id')->all();
        $orphanCompanyNodes = OrgHierarchyNode::where('tenant_id', $tenantId)
            ->where('entity_type', 'COMPANY')
            ->whereNull('deleted_at')
            ->whereNotIn('entity_id', $companyIds === [] ? ['00000000-0000-0000-0000-000000000000'] : $companyIds)
            ->count();

        // Soft-deleted companies still counted via withTrashed for orphan check
        $allCompanyIds = Company::withTrashed()->where('tenant_id', $tenantId)->pluck('company_id')->all();
        $orphanCompanyNodes = OrgHierarchyNode::where('tenant_id', $tenantId)
            ->where('entity_type', 'COMPANY')
            ->whereNull('deleted_at')
            ->when($allCompanyIds !== [], fn ($q) => $q->whereNotIn('entity_id', $allCompanyIds))
            ->when($allCompanyIds === [], fn ($q) => $q)
            ->count();

        if ($orphanCompanyNodes > 0) {
            $issues[] = [
                'code' => 'orphan_company_nodes',
                'count' => $orphanCompanyNodes,
                'message' => 'hierarchy nodes point to missing companies',
            ];
        }

        $status = self::STATUS_HEALTHY;
        if ($issues !== []) {
            $status = self::STATUS_NEEDS_SYNC;
            foreach ($issues as $issue) {
                if (($issue['code'] ?? '') === 'orphan_company_nodes') {
                    $status = self::STATUS_INCONSISTENT;
                    break;
                }
            }
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'counts' => [
                'companies' => $companies->count(),
                'branches' => $branches->count(),
                'business_units' => $bus->count(),
                'issues' => count($issues),
            ],
        ];
    }

    public function rebuildSystemTreesForTenant(?string $tenantId = null): void
    {
        $tenantId = $this->resolveTenantId($tenantId);
        if ($tenantId === '') {
            return;
        }

        $companies = Company::withTrashed()->where('tenant_id', $tenantId)->orderBy('created_at')->get();
        foreach ($companies as $company) {
            $this->syncCompany($company);
        }

        $branches = Branch::withTrashed()->where('tenant_id', $tenantId)->orderBy('created_at')->get();
        foreach ($branches as $branch) {
            $this->syncBranch($branch);
        }

        $bus = BusinessUnit::withTrashed()->where('tenant_id', $tenantId)->orderBy('created_at')->get();
        foreach ($bus as $bu) {
            $this->syncBusinessUnit($bu);
        }
    }

    private function resolveTenantId(mixed $explicit): string
    {
        $tid = (string) ($explicit ?? '');
        if ($tid !== '') {
            return $tid;
        }

        return (string) (TenantContext::getInstance()->getTenantId() ?? '');
    }

    private function ensureSystemHierarchy(
        string $tenantId,
        string $code,
        string $name,
        string $purpose
    ): string {
        $existing = OrgHierarchy::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            if (!(bool) $existing->is_active) {
                $existing->is_active = true;
                $existing->save();
            }

            return (string) $existing->hierarchy_id;
        }

        $h = OrgHierarchy::create([
            'hierarchy_id' => (string) Str::uuid(),
            'tenant_id'    => $tenantId,
            'code'         => $code,
            'name'         => $name,
            'purpose'      => $purpose,
            'version'      => 1,
            'is_active'    => true,
            'row_version'  => 1,
        ]);

        return (string) $h->hierarchy_id;
    }

    private function findNodeId(
        string $tenantId,
        string $hierarchyId,
        string $entityType,
        string $entityId
    ): ?string {
        $node = OrgHierarchyNode::where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchyId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->whereNull('deleted_at')
            ->first();

        return $node ? (string) $node->node_id : null;
    }

    private function upsertNode(
        string $tenantId,
        string $hierarchyId,
        string $entityType,
        string $entityId,
        ?string $parentNodeId,
        bool $isActive
    ): string {
        $existing = OrgHierarchyNode::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchyId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->parent_node_id = $parentNodeId;
            $existing->is_active = $isActive;
            $existing->row_version = ((int) ($existing->row_version ?? 1)) + 1;
            $existing->save();

            return (string) $existing->node_id;
        }

        $node = OrgHierarchyNode::create([
            'node_id'        => (string) Str::uuid(),
            'tenant_id'      => $tenantId,
            'hierarchy_id'   => $hierarchyId,
            'parent_node_id' => $parentNodeId,
            'entity_type'    => $entityType,
            'entity_id'      => $entityId,
            'sort_order'     => 0,
            'is_active'      => $isActive,
            'row_version'    => 1,
        ]);

        return (string) $node->node_id;
    }

    public static function safe(callable $callback): void
    {
        try {
            $callback(app(self::class));
        } catch (\Throwable $e) {
            Log::warning('organization.hierarchy_sync_failed', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }
}
