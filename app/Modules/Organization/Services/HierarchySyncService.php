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

    public function syncBusinessUnit(BusinessUnit $bu): void
    {
        $tenantId = $this->resolveTenantId($bu->tenant_id ?? null);
        if ($tenantId === '') {
            return;
        }

        $activeBuCount = BusinessUnit::where('tenant_id', $tenantId)->where('is_active', true)->count();
        if ($activeBuCount <= 1 && !$bu->trashed()) {
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
            $this->ensureSystemHierarchy($tenantId, self::CODE_LEGAL, 'ساختار حقوقی', OrgHierarchy::PURPOSE_LEGAL);
            $this->ensureSystemHierarchy($tenantId, self::CODE_ESTABLISHMENT, 'استقرار', OrgHierarchy::PURPOSE_ESTABLISHMENT);

            if ($tier !== 'simple') {
                $this->rebuildSystemTreesForTenant($tenantId);
                $rebuilt = true;
            } elseif ($companyCount === 1) {
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

    /**
     * Dry-run: differences vs platform baseline for system trees.
     *
     * @return array{has_changes: bool, summary: array<string, int>, items: list<array{kind: string, message: string}>}
     */
    public function previewRebuild(?string $tenantId = null): array
    {
        $tenantId = $this->resolveTenantId($tenantId);
        $items = [];
        $summary = [
            'hierarchies_to_restore' => 0,
            'nodes_missing' => 0,
            'nodes_soft_deleted' => 0,
            'nodes_orphan' => 0,
        ];

        if ($tenantId === '') {
            return ['has_changes' => false, 'summary' => $summary, 'items' => [['kind' => 'error', 'message' => 'tenant context missing']]];
        }

        $companies = Company::withTrashed()->where('tenant_id', $tenantId)->get();
        $branches = Branch::withTrashed()->where('tenant_id', $tenantId)->get();

        foreach ([
            [self::CODE_LEGAL, 'ساختار حقوقی'],
            [self::CODE_ESTABLISHMENT, 'استقرار'],
            [self::CODE_PRODUCT, 'خطوط محصول'],
        ] as [$code, $label]) {
            $h = OrgHierarchy::withTrashed()->where('tenant_id', $tenantId)->where('code', $code)->first();
            if (!$h) {
                $items[] = ['kind' => 'create_hierarchy', 'message' => "درخت سیستمی «{$label}» ساخته می‌شود."];
                $summary['hierarchies_to_restore']++;
            } elseif ($h->trashed()) {
                $items[] = ['kind' => 'restore_hierarchy', 'message' => "درخت «{$label}» از حذف نرم بازگردانده می‌شود."];
                $summary['hierarchies_to_restore']++;
            }
        }

        $legal = OrgHierarchy::withTrashed()->where('tenant_id', $tenantId)->where('code', self::CODE_LEGAL)->first();
        $est = OrgHierarchy::withTrashed()->where('tenant_id', $tenantId)->where('code', self::CODE_ESTABLISHMENT)->first();

        if ($legal) {
            foreach ($companies as $co) {
                $node = OrgHierarchyNode::withTrashed()
                    ->where('tenant_id', $tenantId)
                    ->where('hierarchy_id', $legal->hierarchy_id)
                    ->where('entity_type', 'COMPANY')
                    ->where('entity_id', $co->company_id)
                    ->first();
                $name = $co->legal_name ?: $co->name ?: $co->code;
                if (!$node) {
                    $items[] = ['kind' => 'missing_node', 'message' => "شرکت «{$name}» در درخت حقوقی اضافه می‌شود."];
                    $summary['nodes_missing']++;
                } elseif ($node->trashed()) {
                    $items[] = ['kind' => 'restore_node', 'message' => "گره شرکت «{$name}» در حقوقی بازگردانده می‌شود."];
                    $summary['nodes_soft_deleted']++;
                }
            }
        }

        if ($est) {
            foreach ($companies as $co) {
                $node = OrgHierarchyNode::withTrashed()
                    ->where('tenant_id', $tenantId)
                    ->where('hierarchy_id', $est->hierarchy_id)
                    ->where('entity_type', 'COMPANY')
                    ->where('entity_id', $co->company_id)
                    ->first();
                $name = $co->legal_name ?: $co->name ?: $co->code;
                if (!$node) {
                    $items[] = ['kind' => 'missing_node', 'message' => "شرکت «{$name}» در استقرار اضافه می‌شود."];
                    $summary['nodes_missing']++;
                } elseif ($node->trashed()) {
                    $items[] = ['kind' => 'restore_node', 'message' => "گره شرکت «{$name}» در استقرار بازگردانده می‌شود."];
                    $summary['nodes_soft_deleted']++;
                }
            }
            foreach ($branches as $br) {
                $node = OrgHierarchyNode::withTrashed()
                    ->where('tenant_id', $tenantId)
                    ->where('hierarchy_id', $est->hierarchy_id)
                    ->where('entity_type', 'BRANCH')
                    ->where('entity_id', $br->branch_id)
                    ->first();
                $name = $br->name ?: $br->code;
                if (!$node) {
                    $items[] = ['kind' => 'missing_node', 'message' => "شعبه «{$name}» در استقرار اضافه می‌شود."];
                    $summary['nodes_missing']++;
                } elseif ($node->trashed()) {
                    $items[] = ['kind' => 'restore_node', 'message' => "گره شعبه «{$name}» بازگردانده می‌شود."];
                    $summary['nodes_soft_deleted']++;
                }
            }
        }

        $allCompanyIds = $companies->pluck('company_id')->all();
        $allBranchIds = $branches->pluck('branch_id')->all();
        foreach ([$legal, $est] as $h) {
            if (!$h) {
                continue;
            }
            foreach (OrgHierarchyNode::where('tenant_id', $tenantId)->where('hierarchy_id', $h->hierarchy_id)->whereNull('deleted_at')->get() as $n) {
                if ($n->entity_type === 'COMPANY' && $allCompanyIds !== [] && !in_array($n->entity_id, $allCompanyIds, true)) {
                    $items[] = ['kind' => 'orphan', 'message' => 'گره یتیم شرکت در درخت سیستمی حذف نرم می‌شود.'];
                    $summary['nodes_orphan']++;
                }
                if ($n->entity_type === 'BRANCH' && $allBranchIds !== [] && !in_array($n->entity_id, $allBranchIds, true)) {
                    $items[] = ['kind' => 'orphan', 'message' => 'گره یتیم شعبه در درخت سیستمی حذف نرم می‌شود.'];
                    $summary['nodes_orphan']++;
                }
            }
        }

        $total = array_sum($summary);
        $has = $total > 0;
        if (!$has) {
            $items[] = ['kind' => 'noop', 'message' => 'تفاوتی با حالت هم‌تراز پلتفرم نیست؛ بازنشانی لازم نیست.'];
        }

        return [
            'has_changes' => $has,
            'summary' => $summary,
            'items' => array_slice($items, 0, 40),
            'items_truncated' => count($items) > 40,
            'counts' => [
                'companies' => $companies->count(),
                'branches' => $branches->count(),
            ],
        ];
    }

    /**
     * Full rebuild of system trees to platform baseline from org entities.
     *
     * @return array{companies:int,branches:int,business_units:int,hierarchies_restored:int,orphans_removed:int}
     */
    public function rebuildSystemTreesForTenant(?string $tenantId = null): array
    {
        $tenantId = $this->resolveTenantId($tenantId);
        if ($tenantId === '') {
            return [
                'companies' => 0,
                'branches' => 0,
                'business_units' => 0,
                'hierarchies_restored' => 0,
                'orphans_removed' => 0,
            ];
        }

        $hRestored = 0;
        foreach ([
            [self::CODE_LEGAL, 'ساختار حقوقی', OrgHierarchy::PURPOSE_LEGAL],
            [self::CODE_ESTABLISHMENT, 'استقرار', OrgHierarchy::PURPOSE_ESTABLISHMENT],
            [self::CODE_PRODUCT, 'خطوط محصول', OrgHierarchy::PURPOSE_CUSTOM],
        ] as [$code, $name, $purpose]) {
            $before = OrgHierarchy::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('code', $code)
                ->first();
            $this->ensureSystemHierarchy($tenantId, $code, $name, $purpose);
            if ($before && $before->trashed()) {
                $hRestored++;
            }
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

        $orphansRemoved = $this->pruneOrphanSystemNodes($tenantId);

        return [
            'companies' => $companies->count(),
            'branches' => $branches->count(),
            'business_units' => $bus->count(),
            'hierarchies_restored' => $hRestored,
            'orphans_removed' => $orphansRemoved,
        ];
    }

    private function pruneOrphanSystemNodes(string $tenantId): int
    {
        $companyIds = Company::withTrashed()->where('tenant_id', $tenantId)->pluck('company_id')->all();
        $branchIds = Branch::withTrashed()->where('tenant_id', $tenantId)->pluck('branch_id')->all();
        $buIds = BusinessUnit::withTrashed()->where('tenant_id', $tenantId)->pluck('business_unit_id')->all();

        $sysIds = OrgHierarchy::where('tenant_id', $tenantId)
            ->whereIn('code', [self::CODE_LEGAL, self::CODE_ESTABLISHMENT, self::CODE_PRODUCT])
            ->pluck('hierarchy_id')
            ->all();
        if ($sysIds === []) {
            return 0;
        }

        $removed = 0;
        $nodes = OrgHierarchyNode::where('tenant_id', $tenantId)
            ->whereIn('hierarchy_id', $sysIds)
            ->whereNull('deleted_at')
            ->get();

        foreach ($nodes as $n) {
            $missing = false;
            if ($n->entity_type === 'COMPANY' && !in_array($n->entity_id, $companyIds, true)) {
                $missing = true;
            } elseif ($n->entity_type === 'BRANCH' && !in_array($n->entity_id, $branchIds, true)) {
                $missing = true;
            } elseif ($n->entity_type === 'BUSINESS_UNIT' && !in_array($n->entity_id, $buIds, true)) {
                $missing = true;
            }
            if ($missing) {
                $n->delete();
                $removed++;
            }
        }

        return $removed;
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
        $node = OrgHierarchyNode::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchyId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->first();

        if (!$node) {
            return null;
        }

        if ($node->trashed()) {
            $node->restore();
        }

        return (string) $node->node_id;
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
