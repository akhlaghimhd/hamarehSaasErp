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
 * System trees ONLY from companies / branches / BUs.
 * rebuildSystemTreesForTenant = explicit day-one wipe+rebuild.
 * ensureStructuralTrees / syncAllFromOrgBoxes = non-destructive sync.
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

        $legalId = $this->ensureSystemHierarchy($tenantId, self::CODE_LEGAL, 'ساختار حقوقی', OrgHierarchy::PURPOSE_LEGAL);

        $parentNodeId = null;
        if (!empty($company->parent_company_id)) {
            $parentNodeId = $this->findNodeId($tenantId, $legalId, 'COMPANY', (string) $company->parent_company_id);
            if ($parentNodeId === null) {
                $parent = Company::withTrashed()->where('tenant_id', $tenantId)->where('company_id', $company->parent_company_id)->first();
                if ($parent) {
                    $parentNodeId = $this->upsertNode($tenantId, $legalId, 'COMPANY', (string) $parent->company_id, null, !$parent->trashed() && $parent->is_active !== false);
                }
            }
        }

        $active = !$company->trashed() && $company->is_active !== false;
        $this->upsertNode($tenantId, $legalId, 'COMPANY', (string) $company->company_id, $parentNodeId, $active);

        $estId = $this->ensureSystemHierarchy($tenantId, self::CODE_ESTABLISHMENT, 'استقرار', OrgHierarchy::PURPOSE_ESTABLISHMENT);
        $this->upsertNode($tenantId, $estId, 'COMPANY', (string) $company->company_id, null, $active);
    }

    public function syncBranch(Branch $branch): void
    {
        $tenantId = $this->resolveTenantId($branch->tenant_id ?? null);
        if ($tenantId === '' || empty($branch->company_id)) {
            return;
        }

        $estId = $this->ensureSystemHierarchy($tenantId, self::CODE_ESTABLISHMENT, 'استقرار', OrgHierarchy::PURPOSE_ESTABLISHMENT);
        $companyNodeId = $this->findNodeId($tenantId, $estId, 'COMPANY', (string) $branch->company_id);
        if ($companyNodeId === null) {
            $co = Company::withTrashed()->where('tenant_id', $tenantId)->where('company_id', $branch->company_id)->first();
            if ($co) {
                $companyNodeId = $this->upsertNode($tenantId, $estId, 'COMPANY', (string) $co->company_id, null, !$co->trashed() && $co->is_active !== false);
            }
        }

        $active = !$branch->trashed() && $branch->is_active !== false;
        $this->upsertNode($tenantId, $estId, 'BRANCH', (string) $branch->branch_id, $companyNodeId, $active);
    }

    public function syncBranchesForCompany(string $companyId): void
    {
        $tenantId = $this->resolveTenantId(null);
        if ($tenantId === '') {
            return;
        }
        foreach (Branch::withTrashed()->where('tenant_id', $tenantId)->where('company_id', $companyId)->get() as $branch) {
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

        $productId = $this->ensureSystemHierarchy($tenantId, self::CODE_PRODUCT, 'خطوط محصول', OrgHierarchy::PURPOSE_CUSTOM);
        $primaryCompanyId = BusinessUnitCompany::where('tenant_id', $tenantId)->where('business_unit_id', $bu->business_unit_id)->where('is_primary', true)->value('company_id');
        if (!$primaryCompanyId) {
            $primaryCompanyId = BusinessUnitCompany::where('tenant_id', $tenantId)->where('business_unit_id', $bu->business_unit_id)->orderBy('created_at')->value('company_id');
        }

        $parentNodeId = null;
        if ($primaryCompanyId) {
            $parentNodeId = $this->findNodeId($tenantId, $productId, 'COMPANY', (string) $primaryCompanyId);
            if ($parentNodeId === null) {
                $co = Company::withTrashed()->where('tenant_id', $tenantId)->where('company_id', $primaryCompanyId)->first();
                if ($co) {
                    $parentNodeId = $this->upsertNode($tenantId, $productId, 'COMPANY', (string) $co->company_id, null, !$co->trashed() && $co->is_active !== false);
                }
            }
        }

        $active = !$bu->trashed() && $bu->is_active !== false;
        $this->upsertNode($tenantId, $productId, 'BUSINESS_UNIT', (string) $bu->business_unit_id, $parentNodeId, $active);
    }

    public function deactivateEntityNodes(string $entityType, string $entityId): void
    {
        $tenantId = $this->resolveTenantId(null);
        if ($tenantId === '') return;
        OrgHierarchyNode::where('tenant_id', $tenantId)->where('entity_type', strtoupper($entityType))->where('entity_id', $entityId)->whereNull('deleted_at')
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function reactivateEntityNodes(string $entityType, string $entityId): void
    {
        $tenantId = $this->resolveTenantId(null);
        if ($tenantId === '') return;
        OrgHierarchyNode::where('tenant_id', $tenantId)->where('entity_type', strtoupper($entityType))->where('entity_id', $entityId)->whereNull('deleted_at')
            ->update(['is_active' => true, 'updated_at' => now()]);
    }

    public function ensureStructuralTrees(?string $tenantId = null): array
    {
        $tenantId = $this->resolveTenantId($tenantId);
        if ($tenantId === '') {
            return ['tier' => 'unknown', 'rebuilt' => false, 'company_count' => 0, 'branch_count' => 0, 'bu_count' => 0];
        }

        $companyCount = Company::where('tenant_id', $tenantId)->count();
        $branchCount = Branch::where('tenant_id', $tenantId)->count();
        $buCount = BusinessUnit::where('tenant_id', $tenantId)->count();

        $tier = 'simple';
        if ($companyCount > 1 || $buCount > 1) {
            $tier = 'advanced';
        } elseif ($branchCount > 1) {
            $tier = 'standard';
        }

        if ($companyCount >= 1) {
            $this->ensureSystemHierarchy($tenantId, self::CODE_LEGAL, 'ساختار حقوقی', OrgHierarchy::PURPOSE_LEGAL);
            $this->ensureSystemHierarchy($tenantId, self::CODE_ESTABLISHMENT, 'استقرار', OrgHierarchy::PURPOSE_ESTABLISHMENT);
            $this->syncAllFromOrgBoxes($tenantId);
        }

        if ($buCount > 1) {
            $this->ensureSystemHierarchy($tenantId, self::CODE_PRODUCT, 'خطوط محصول', OrgHierarchy::PURPOSE_CUSTOM);
            foreach (BusinessUnit::where('tenant_id', $tenantId)->get() as $bu) {
                $this->syncBusinessUnit($bu);
            }
        }

        return ['tier' => $tier, 'rebuilt' => false, 'company_count' => $companyCount, 'branch_count' => $branchCount, 'bu_count' => $buCount];
    }

    public function syncAllFromOrgBoxes(?string $tenantId = null): void
    {
        $tenantId = $this->resolveTenantId($tenantId);
        if ($tenantId === '') return;

        foreach (Company::withTrashed()->where('tenant_id', $tenantId)->orderBy('created_at')->get() as $company) {
            $this->syncCompany($company);
        }
        foreach (Branch::withTrashed()->where('tenant_id', $tenantId)->orderBy('created_at')->get() as $branch) {
            $this->syncBranch($branch);
        }
        foreach (BusinessUnit::withTrashed()->where('tenant_id', $tenantId)->orderBy('created_at')->get() as $bu) {
            $this->syncBusinessUnit($bu);
        }
    }

    public function health(?string $tenantId = null): array
    {
        $tenantId = $this->resolveTenantId($tenantId);
        if ($tenantId === '') {
            return ['status' => self::STATUS_INCONSISTENT, 'issues' => [['code' => 'no_tenant']], 'counts' => []];
        }
        $companies = Company::where('tenant_id', $tenantId)->get();
        $issues = [];
        if ($companies->isNotEmpty() && !OrgHierarchy::where('tenant_id', $tenantId)->where('code', self::CODE_LEGAL)->exists()) {
            $issues[] = ['code' => 'missing_legal_tree'];
        }
        if ($companies->isNotEmpty() && !OrgHierarchy::where('tenant_id', $tenantId)->where('code', self::CODE_ESTABLISHMENT)->exists()) {
            $issues[] = ['code' => 'missing_establishment_tree'];
        }
        return [
            'status' => $issues === [] ? self::STATUS_HEALTHY : self::STATUS_NEEDS_SYNC,
            'issues' => $issues,
            'counts' => [
                'companies' => $companies->count(),
                'branches' => Branch::where('tenant_id', $tenantId)->count(),
                'business_units' => BusinessUnit::where('tenant_id', $tenantId)->count(),
                'issues' => count($issues),
            ],
        ];
    }

    public function previewRebuild(?string $tenantId = null): array
    {
        $tenantId = $this->resolveTenantId($tenantId);
        $items = [];
        $summary = ['custom_hierarchies_to_remove' => 0, 'hierarchies_to_restore' => 0, 'extra_nodes_to_remove' => 0, 'nodes_to_create' => 0];
        if ($tenantId === '') {
            return ['has_changes' => false, 'summary' => $summary, 'items' => [['kind' => 'error', 'message' => 'tenant context missing']]];
        }

        $companies = Company::where('tenant_id', $tenantId)->get();
        $branches = Branch::where('tenant_id', $tenantId)->get();
        $bus = BusinessUnit::where('tenant_id', $tenantId)->get();
        $sysCodes = [self::CODE_LEGAL, self::CODE_ESTABLISHMENT, self::CODE_PRODUCT];

        foreach (OrgHierarchy::where('tenant_id', $tenantId)->whereNotIn('code', $sysCodes)->get() as $h) {
            $items[] = ['kind' => 'remove_custom', 'message' => "درخت سفارشی «{$h->name}» حذف نرم می‌شود."];
            $summary['custom_hierarchies_to_remove']++;
        }

        $validPairs = [];
        foreach ($companies as $co) { $validPairs['COMPANY:'.$co->company_id] = true; }
        foreach ($branches as $br) { $validPairs['BRANCH:'.$br->branch_id] = true; }
        foreach ($bus as $bu) { $validPairs['BUSINESS_UNIT:'.$bu->business_unit_id] = true; }

        foreach ($sysCodes as $code) {
            $label = match ($code) {
                self::CODE_LEGAL => 'ساختار حقوقی',
                self::CODE_ESTABLISHMENT => 'استقرار',
                default => 'خطوط محصول',
            };
            $h = OrgHierarchy::withTrashed()->where('tenant_id', $tenantId)->where('code', $code)->first();
            if (!$h) {
                $items[] = ['kind' => 'create_hierarchy', 'message' => "درخت «{$label}» ساخته می‌شود."];
                $summary['hierarchies_to_restore']++;
                continue;
            }
            if ($h->deleted_at !== null || $h->trashed()) {
                $items[] = ['kind' => 'restore_hierarchy', 'message' => "درخت «{$label}» بازگردانده می‌شود."];
                $summary['hierarchies_to_restore']++;
            }
            foreach (OrgHierarchyNode::where('tenant_id', $tenantId)->where('hierarchy_id', $h->hierarchy_id)->whereNull('deleted_at')->get() as $n) {
                $key = $n->entity_type.':'.$n->entity_id;
                $allowed = match ($code) {
                    self::CODE_LEGAL => $n->entity_type === 'COMPANY',
                    self::CODE_ESTABLISHMENT => in_array($n->entity_type, ['COMPANY', 'BRANCH'], true),
                    self::CODE_PRODUCT => in_array($n->entity_type, ['COMPANY', 'BUSINESS_UNIT'], true),
                    default => false,
                };
                if (!$allowed || !isset($validPairs[$key])) {
                    $summary['extra_nodes_to_remove']++;
                }
            }
        }

        if ($summary['extra_nodes_to_remove'] > 0) {
            $items[] = ['kind' => 'remove_extra_nodes', 'message' => "{$summary['extra_nodes_to_remove']} گره غیرپایه حذف می‌شود."];
        }
        $items[] = ['kind' => 'full_baseline', 'message' => 'درخت‌های سیستمی از نو فقط از روی شرکت و شعبه ساخته می‌شوند (روز اول). درخت سفارشی حذف می‌شود.'];

        $has = $companies->isNotEmpty() || $summary['custom_hierarchies_to_remove'] > 0 || $summary['extra_nodes_to_remove'] > 0 || $summary['hierarchies_to_restore'] > 0;

        return [
            'has_changes' => $has,
            'summary' => $summary,
            'items' => array_slice($items, 0, 50),
            'counts' => ['companies' => $companies->count(), 'branches' => $branches->count(), 'business_units' => $bus->count()],
            'note' => 'full_platform_baseline',
        ];
    }

    public function rebuildSystemTreesForTenant(?string $tenantId = null): array
    {
        $tenantId = $this->resolveTenantId($tenantId);
        if ($tenantId === '') {
            return ['companies' => 0, 'branches' => 0, 'business_units' => 0, 'hierarchies_restored' => 0, 'custom_removed' => 0, 'nodes_wiped' => 0, 'orphans_removed' => 0];
        }

        $sysCodes = [self::CODE_LEGAL, self::CODE_ESTABLISHMENT, self::CODE_PRODUCT];

        $customRemoved = 0;
        foreach (OrgHierarchy::where('tenant_id', $tenantId)->whereNotIn('code', $sysCodes)->get() as $h) {
            OrgHierarchyNode::where('tenant_id', $tenantId)->where('hierarchy_id', $h->hierarchy_id)->get()->each(fn (OrgHierarchyNode $n) => $n->delete());
            $h->delete();
            $customRemoved++;
        }

        $hRestored = 0;
        foreach ([
            [self::CODE_LEGAL, 'ساختار حقوقی', OrgHierarchy::PURPOSE_LEGAL],
            [self::CODE_ESTABLISHMENT, 'استقرار', OrgHierarchy::PURPOSE_ESTABLISHMENT],
            [self::CODE_PRODUCT, 'خطوط محصول', OrgHierarchy::PURPOSE_CUSTOM],
        ] as [$code, $name, $purpose]) {
            $before = OrgHierarchy::withTrashed()->where('tenant_id', $tenantId)->where('code', $code)->first();
            $this->ensureSystemHierarchy($tenantId, $code, $name, $purpose);
            if ($before && ($before->trashed() || $before->deleted_at !== null)) {
                $hRestored++;
            }
        }

        $sysIds = OrgHierarchy::where('tenant_id', $tenantId)->whereIn('code', $sysCodes)->pluck('hierarchy_id')->all();
        $nodesWiped = 0;
        if ($sysIds !== []) {
            foreach (OrgHierarchyNode::where('tenant_id', $tenantId)->whereIn('hierarchy_id', $sysIds)->whereNull('deleted_at')->get() as $n) {
                $n->delete();
                $nodesWiped++;
            }
        }

        $this->syncAllFromOrgBoxes($tenantId);

        $orphansRemoved = $this->pruneOrphanSystemNodes($tenantId);

        return [
            'companies' => Company::where('tenant_id', $tenantId)->count(),
            'branches' => Branch::where('tenant_id', $tenantId)->count(),
            'business_units' => BusinessUnit::where('tenant_id', $tenantId)->count(),
            'hierarchies_restored' => $hRestored,
            'custom_removed' => $customRemoved,
            'nodes_wiped' => $nodesWiped,
            'orphans_removed' => $orphansRemoved,
            'system_trees' => ['SYS-LEGAL', 'SYS-ESTABLISHMENT', 'SYS-PRODUCT'],
        ];
    }

    private function pruneOrphanSystemNodes(string $tenantId): int
    {
        $companyIds = Company::withTrashed()->where('tenant_id', $tenantId)->pluck('company_id')->all();
        $branchIds = Branch::withTrashed()->where('tenant_id', $tenantId)->pluck('branch_id')->all();
        $buIds = BusinessUnit::withTrashed()->where('tenant_id', $tenantId)->pluck('business_unit_id')->all();
        $sysIds = OrgHierarchy::where('tenant_id', $tenantId)->whereIn('code', [self::CODE_LEGAL, self::CODE_ESTABLISHMENT, self::CODE_PRODUCT])->pluck('hierarchy_id')->all();
        if ($sysIds === []) return 0;

        $removed = 0;
        foreach (OrgHierarchyNode::where('tenant_id', $tenantId)->whereIn('hierarchy_id', $sysIds)->whereNull('deleted_at')->get() as $n) {
            $missing = match (true) {
                $n->entity_type === 'COMPANY' && !in_array($n->entity_id, $companyIds, true) => true,
                $n->entity_type === 'BRANCH' && !in_array($n->entity_id, $branchIds, true) => true,
                $n->entity_type === 'BUSINESS_UNIT' && !in_array($n->entity_id, $buIds, true) => true,
                !in_array($n->entity_type, ['COMPANY', 'BRANCH', 'BUSINESS_UNIT'], true) => true,
                default => false,
            };
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
        return $tid !== '' ? $tid : (string) (TenantContext::getInstance()->getTenantId() ?? '');
    }

    private function ensureSystemHierarchy(string $tenantId, string $code, string $name, string $purpose): string
    {
        $existing = OrgHierarchy::withTrashed()->where('tenant_id', $tenantId)->where('code', $code)->first();
        if ($existing) {
            if ($existing->trashed() || $existing->deleted_at !== null) {
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
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => $name,
            'purpose' => $purpose,
            'version' => 1,
            'is_active' => true,
            'row_version' => 1,
        ]);
        return (string) $h->hierarchy_id;
    }

    private function findNodeId(string $tenantId, string $hierarchyId, string $entityType, string $entityId): ?string
    {
        $node = OrgHierarchyNode::withTrashed()->where('tenant_id', $tenantId)->where('hierarchy_id', $hierarchyId)->where('entity_type', $entityType)->where('entity_id', $entityId)->first();
        if (!$node) return null;
        if ($node->trashed() || $node->deleted_at !== null) {
            $node->restore();
        }
        return (string) $node->node_id;
    }

    private function upsertNode(string $tenantId, string $hierarchyId, string $entityType, string $entityId, ?string $parentNodeId, bool $isActive): string
    {
        $existing = OrgHierarchyNode::withTrashed()->where('tenant_id', $tenantId)->where('hierarchy_id', $hierarchyId)->where('entity_type', $entityType)->where('entity_id', $entityId)->first();
        if ($existing) {
            if ($existing->trashed() || $existing->deleted_at !== null) {
                $existing->restore();
            }
            // D3: never reposition MANUAL nodes during point-sync
            $origin = strtoupper((string) ($existing->node_origin ?? OrgHierarchyNode::ORIGIN_SYSTEM));
            if ($origin !== OrgHierarchyNode::ORIGIN_MANUAL) {
                $existing->parent_node_id = $parentNodeId;
                $existing->node_origin = OrgHierarchyNode::ORIGIN_SYSTEM;
            }
            $existing->is_active = $isActive;
            $existing->row_version = ((int) ($existing->row_version ?? 1)) + 1;
            $existing->save();
            return (string) $existing->node_id;
        }
        $node = OrgHierarchyNode::create([
            'node_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'hierarchy_id' => $hierarchyId,
            'parent_node_id' => $parentNodeId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'node_origin' => OrgHierarchyNode::ORIGIN_SYSTEM,
            'sort_order' => 0,
            'is_active' => $isActive,
            'row_version' => 1,
        ]);
        return (string) $node->node_id;
    }

    public static function safe(callable $callback): void
    {
        try {
            $callback(app(self::class));
        } catch (\Throwable $e) {
            Log::warning('organization.hierarchy_sync_failed', ['message' => $e->getMessage(), 'exception' => $e::class]);
        }
    }
}
