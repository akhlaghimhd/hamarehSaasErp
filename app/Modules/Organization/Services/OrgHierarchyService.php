<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\OrgHierarchy;
use App\Modules\Organization\Models\OrgHierarchyNode;
use App\Base\Context\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OrgHierarchyService
{
    /** Allowed entity types per hierarchy purpose (product law). */
    public const PURPOSE_ENTITY_MAP = [
        'LEGAL'         => ['COMPANY'],
        'ESTABLISHMENT' => ['COMPANY', 'BRANCH'],
        'MANAGEMENT'    => ['COMPANY', 'BUSINESS_UNIT', 'DEPARTMENT', 'COST_CENTER'],
        'TAX'           => ['COMPANY'],
        'CUSTOM'        => ['COMPANY', 'BRANCH', 'DEPARTMENT', 'BUSINESS_UNIT', 'COST_CENTER'],
    ];

    public function createHierarchy(
        string $code,
        string $name,
        string $purpose,
        ?string $validFrom = null,
        ?string $validTo = null,
    ): OrgHierarchy {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $purpose = strtoupper(trim($purpose));

        if (!in_array($purpose, OrgHierarchy::PURPOSES, true)) {
            throw new \Exception('هدف سلسله‌مراتب نامعتبر است. مقادیر مجاز: LEGAL, MANAGEMENT, TAX, ESTABLISHMENT, CUSTOM');
        }

        if (OrgHierarchy::where('tenant_id', $tenantId)->where('code', $code)->exists()) {
            throw new \Exception('کد سلسله‌مراتب تکراری است.');
        }

        return OrgHierarchy::create([
            'hierarchy_id' => (string) Str::uuid(),
            'tenant_id'    => $tenantId,
            'code'         => $code,
            'name'         => $name,
            'purpose'      => $purpose,
            'version'      => 1,
            'valid_from'   => $validFrom,
            'valid_to'     => $validTo,
            'is_active'    => true,
            'row_version'  => 1,
        ]);
    }

    public function listHierarchies(bool $onlyTrashed = false)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $q = OrgHierarchy::where('tenant_id', $tenantId);
        if ($onlyTrashed) {
            $q->onlyTrashed();
        }

        return $q->orderBy('purpose')->orderBy('code')->get();
    }

    public function listNodes(string $hierarchyId, bool $onlyTrashed = false): Collection
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        OrgHierarchy::where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchyId)
            ->firstOrFail();

        $q = OrgHierarchyNode::where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchyId);

        if ($onlyTrashed) {
            $q->onlyTrashed();
        }

        return $q->orderBy('sort_order')->orderBy('created_at')->get();
    }

    public function addNode(
        string $hierarchyId,
        string $entityType,
        string $entityId,
        ?string $parentNodeId = null,
        int $sortOrder = 0,
    ): OrgHierarchyNode {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $entityType = strtoupper(trim($entityType));

        if (!in_array($entityType, OrgHierarchyNode::ENTITY_TYPES, true)) {
            throw new \Exception('نوع موجودیت گره نامعتبر است.');
        }

        $hierarchy = OrgHierarchy::where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchyId)
            ->firstOrFail();

        $this->assertEntityAllowedForPurpose($hierarchy->purpose, $entityType);

        // Prevent duplicate of same entity in the same tree (active rows).
        $dup = OrgHierarchyNode::where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchyId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->exists();
        if ($dup) {
            throw new \Exception('این مورد از قبل در همین سلسله‌مراتب ثبت شده است. تکرار مجاز نیست.');
        }

        $parent = null;
        if ($parentNodeId) {
            $parent = OrgHierarchyNode::where('tenant_id', $tenantId)
                ->where('node_id', $parentNodeId)
                ->where('hierarchy_id', $hierarchyId)
                ->first();
            if (!$parent) {
                throw new \Exception('گره والد در این سلسله‌مراتب یافت نشد.');
            }
            if ($parent->is_active === false) {
                throw new \Exception('نمی‌توان زیر گره غیرفعال، زیرشاخه ساخت. ابتدا والد را فعال کنید.');
            }
            $this->assertParentChildRules($hierarchy->purpose, $entityType, $parent);
        } else {
            $this->assertRootRules($hierarchy->purpose, $entityType);
        }

        return OrgHierarchyNode::create([
            'node_id'        => (string) Str::uuid(),
            'tenant_id'      => $tenantId,
            'hierarchy_id'   => $hierarchyId,
            'parent_node_id' => $parentNodeId,
            'entity_type'    => $entityType,
            'entity_id'      => $entityId,
            'sort_order'     => $sortOrder,
            'is_active'      => true,
            'row_version'    => 1,
        ]);
    }

    /**
     * Soft-delete a node and all descendants (structure stays consistent; no orphan children).
     * System trees may reappear after rebuild — caller should warn the user.
     */
    public function softDeleteNode(string $nodeId): int
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $node = OrgHierarchyNode::where('tenant_id', $tenantId)
            ->where('node_id', $nodeId)
            ->firstOrFail();

        $ids = $this->collectDescendantIds($tenantId, $node->hierarchy_id, $nodeId);
        $ids[] = $nodeId;
        $ids = array_values(array_unique($ids));

        $count = 0;
        foreach ($ids as $id) {
            $n = OrgHierarchyNode::where('tenant_id', $tenantId)->where('node_id', $id)->first();
            if ($n) {
                $n->delete();
                $count++;
            }
        }

        return $count;
    }

    public function restoreNode(string $nodeId): OrgHierarchyNode
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $node = OrgHierarchyNode::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->where('node_id', $nodeId)
            ->firstOrFail();

        // Restore ancestors first so parent chain is intact.
        $this->restoreAncestors($tenantId, $node);
        $node->restore();

        return $node->fresh();
    }

    public function setNodeActive(string $nodeId, bool $active): OrgHierarchyNode
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $node = OrgHierarchyNode::where('tenant_id', $tenantId)
            ->where('node_id', $nodeId)
            ->firstOrFail();

        if ($active && $node->parent_node_id) {
            $parent = OrgHierarchyNode::where('tenant_id', $tenantId)
                ->where('node_id', $node->parent_node_id)
                ->first();
            if ($parent && $parent->is_active === false) {
                throw new \Exception('والد این گره غیرفعال است. ابتدا والد را فعال کنید.');
            }
        }

        $node->is_active = $active;
        $node->row_version = (int) $node->row_version + 1;
        $node->save();

        // Deactivating cascades to descendants so structure does not look "half active".
        if (!$active) {
            $desc = $this->collectDescendantIds($tenantId, $node->hierarchy_id, $nodeId);
            if ($desc !== []) {
                OrgHierarchyNode::where('tenant_id', $tenantId)
                    ->whereIn('node_id', $desc)
                    ->update([
                        'is_active'   => false,
                        'row_version' => \DB::raw('row_version + 1'),
                    ]);
            }
        }

        return $node->fresh();
    }

    public function softDeleteHierarchy(string $hierarchyId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $h = OrgHierarchy::where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchyId)
            ->firstOrFail();

        // Soft-delete all nodes then hierarchy.
        OrgHierarchyNode::where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchyId)
            ->get()
            ->each(fn (OrgHierarchyNode $n) => $n->delete());

        $h->delete();
    }

    public function restoreHierarchy(string $hierarchyId): OrgHierarchy
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $h = OrgHierarchy::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchyId)
            ->firstOrFail();

        $h->restore();

        OrgHierarchyNode::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchyId)
            ->restore();

        return $h->fresh();
    }

    public function setHierarchyActive(string $hierarchyId, bool $active): OrgHierarchy
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $h = OrgHierarchy::where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchyId)
            ->firstOrFail();

        $h->is_active = $active;
        $h->row_version = (int) $h->row_version + 1;
        $h->save();

        return $h->fresh();
    }

    public function bulkSetNodesActive(array $nodeIds, bool $active): int
    {
        $count = 0;
        foreach (array_unique($nodeIds) as $id) {
            try {
                $this->setNodeActive((string) $id, $active);
                $count++;
            } catch (\Throwable) {
                // skip invalid
            }
        }

        return $count;
    }

    public function bulkSoftDeleteNodes(array $nodeIds): int
    {
        $count = 0;
        // Delete deepest first is safer; softDeleteNode already cascades children.
        foreach (array_unique($nodeIds) as $id) {
            try {
                $count += $this->softDeleteNode((string) $id);
            } catch (\Throwable) {
                // skip
            }
        }

        return $count;
    }

    protected function assertEntityAllowedForPurpose(string $purpose, string $entityType): void
    {
        $allowed = self::PURPOSE_ENTITY_MAP[strtoupper($purpose)] ?? null;
        if ($allowed === null) {
            return;
        }
        if (!in_array($entityType, $allowed, true)) {
            throw new \Exception(
                "نوع «{$entityType}» برای هدف «{$purpose}» مجاز نیست. "
                .'انواع مجاز: '.implode(', ', $allowed)
            );
        }
    }

    protected function assertRootRules(string $purpose, string $entityType): void
    {
        $purpose = strtoupper($purpose);
        // LEGAL / TAX / ESTABLISHMENT roots should be COMPANY.
        if (in_array($purpose, ['LEGAL', 'TAX', 'ESTABLISHMENT'], true) && $entityType !== 'COMPANY') {
            throw new \Exception('ریشهٔ این نوع درخت باید شرکت باشد. «ریشه» یعنی بالاترین سطح نقشه بدون والد.');
        }
    }

    protected function assertParentChildRules(string $purpose, string $childType, OrgHierarchyNode $parent): void
    {
        $purpose = strtoupper($purpose);
        $parentType = $parent->entity_type;

        if ($purpose === 'LEGAL' || $purpose === 'TAX') {
            // Only company under company.
            if ($childType !== 'COMPANY' || $parentType !== 'COMPANY') {
                throw new \Exception('در درخت حقوقی/مالیاتی فقط شرکت زیر شرکت مجاز است.');
            }
        }

        if ($purpose === 'ESTABLISHMENT') {
            if ($childType === 'BRANCH' && $parentType !== 'COMPANY') {
                throw new \Exception('شعبه فقط می‌تواند زیر شرکت قرار گیرد (نه زیر شعبهٔ دیگر در این نقشه).');
            }
            if ($childType === 'COMPANY' && $parentType !== 'COMPANY') {
                throw new \Exception('شرکت فقط زیر شرکت (گروه) قرار می‌گیرد.');
            }
        }
    }

    /** @return list<string> */
    protected function collectDescendantIds(string $tenantId, string $hierarchyId, string $rootNodeId): array
    {
        $all = OrgHierarchyNode::where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchyId)
            ->get(['node_id', 'parent_node_id']);

        $byParent = [];
        foreach ($all as $n) {
            $pid = $n->parent_node_id ?? '';
            $byParent[$pid][] = $n->node_id;
        }

        $out = [];
        $stack = $byParent[$rootNodeId] ?? [];
        while ($stack !== []) {
            $id = array_pop($stack);
            $out[] = $id;
            foreach ($byParent[$id] ?? [] as $child) {
                $stack[] = $child;
            }
        }

        return $out;
    }

    protected function restoreAncestors(string $tenantId, OrgHierarchyNode $node): void
    {
        $pid = $node->parent_node_id;
        $guard = 0;
        while ($pid && $guard < 50) {
            $parent = OrgHierarchyNode::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('node_id', $pid)
                ->first();
            if (!$parent) {
                break;
            }
            if ($parent->trashed()) {
                $parent->restore();
            }
            $pid = $parent->parent_node_id;
            $guard++;
        }
    }
}
