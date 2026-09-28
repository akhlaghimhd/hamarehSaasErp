<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\OrgHierarchy;
use App\Modules\Organization\Models\OrgHierarchyNode;
use App\Base\Context\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrgHierarchyService
{
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
        $code = trim($code);

        // Product law: API-created trees are CUSTOM (reporting) only. SYS trees come from sync.
        $purpose = 'CUSTOM';

        if (!filter_var(env('FEATURE_CUSTOM_ORG_HIERARCHY', false), FILTER_VALIDATE_BOOLEAN)) {
            throw new \Exception('قابلیت درخت سلسله‌مراتب سفارشی برای این محیط فعال نیست. پس از خرید پک در کاتالوگ ویژگی فعال می‌شود.');
        }

        if (str_starts_with(strtoupper($code), 'SYS-') || in_array($code, OrgHierarchy::SYSTEM_CODES, true)) {
            throw new \Exception('کد با پیشوند SYS برای درخت‌های سیستمی پلتفرم رزرو شده است.');
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

    public function updateHierarchy(
        string $hierarchyId,
        string $name,
        ?string $code = null,
        ?string $validFrom = null,
        ?string $validTo = null,
    ): OrgHierarchy {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $h = OrgHierarchy::where('tenant_id', $tenantId)->where('hierarchy_id', $hierarchyId)->firstOrFail();

        if ($h->is_system) {
            $h->name = $name;
            $h->row_version = (int) $h->row_version + 1;
            $h->save();
            return $h->fresh();
        }

        if ($code !== null && $code !== '') {
            $code = trim($code);
            if (str_starts_with(strtoupper($code), 'SYS-')) {
                throw new \Exception('کد با پیشوند SYS رزرو شده است.');
            }
            $dup = OrgHierarchy::where('tenant_id', $tenantId)
                ->where('code', $code)
                ->where('hierarchy_id', '!=', $hierarchyId)
                ->exists();
            if ($dup) {
                throw new \Exception('کد سلسله‌مراتب تکراری است.');
            }
            $h->code = $code;
        }

        $h->name = $name;
        if ($validFrom !== null) {
            $h->valid_from = $validFrom !== '' ? $validFrom : null;
        }
        if ($validTo !== null) {
            $h->valid_to = $validTo !== '' ? $validTo : null;
        }
        $h->row_version = (int) $h->row_version + 1;
        $h->save();

        return $h->fresh();
    }

    public function listHierarchies(bool $onlyTrashed = false)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $q = OrgHierarchy::where('tenant_id', $tenantId)->withCount([
            'nodes as nodes_count' => function ($q) {
                $q->whereNull('deleted_at');
            },
        ]);
        if ($onlyTrashed) {
            $q->onlyTrashed();
        }

        return $q->orderBy('purpose')->orderBy('code')->get();
    }

    public function listNodes(string $hierarchyId, bool $onlyTrashed = false): Collection
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $hierQ = OrgHierarchy::where('tenant_id', $tenantId)->where('hierarchy_id', $hierarchyId);
        if ($onlyTrashed) {
            $hierQ->withTrashed();
        }
        $hierQ->firstOrFail();

        $q = OrgHierarchyNode::where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchyId);

        if ($onlyTrashed) {
            $q->onlyTrashed();
        }

        $nodes = $q->orderBy('sort_order')->orderBy('created_at')->get();

        return $this->enrichNodesWithEntityLabels((string) $tenantId, $nodes);
    }

    protected function enrichNodesWithEntityLabels(string $tenantId, Collection $nodes): Collection
    {
        if ($nodes->isEmpty()) {
            return $nodes;
        }

        $byType = [];
        foreach ($nodes as $n) {
            $t = strtoupper((string) $n->entity_type);
            $byType[$t][] = (string) $n->entity_id;
        }

        $labels = [];

        if (!empty($byType['COMPANY'])) {
            $rows = \App\Modules\Organization\Models\Company::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('company_id', array_unique($byType['COMPANY']))
                ->get(['company_id', 'code', 'name', 'legal_name']);
            foreach ($rows as $r) {
                $labels['COMPANY:'.$r->company_id] = [
                    (string) ($r->legal_name ?: $r->name ?: $r->code ?: 'شرکت'),
                    (string) ($r->code ?? ''),
                ];
            }
        }
        if (!empty($byType['BRANCH'])) {
            $rows = \App\Modules\Organization\Models\Branch::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('branch_id', array_unique($byType['BRANCH']))
                ->get(['branch_id', 'code', 'name']);
            foreach ($rows as $r) {
                $labels['BRANCH:'.$r->branch_id] = [
                    (string) ($r->name ?: $r->code ?: 'شعبه'),
                    (string) ($r->code ?? ''),
                ];
            }
        }
        if (!empty($byType['BUSINESS_UNIT'])) {
            $rows = \App\Modules\Organization\Models\BusinessUnit::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('business_unit_id', array_unique($byType['BUSINESS_UNIT']))
                ->get(['business_unit_id', 'code', 'name']);
            foreach ($rows as $r) {
                $labels['BUSINESS_UNIT:'.$r->business_unit_id] = [
                    (string) ($r->name ?: $r->code ?: 'واحد کسب‌وکار'),
                    (string) ($r->code ?? ''),
                ];
            }
        }
        if (!empty($byType['DEPARTMENT'])) {
            $rows = \App\Modules\Organization\Models\Department::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('department_id', array_unique($byType['DEPARTMENT']))
                ->get(['department_id', 'code', 'name']);
            foreach ($rows as $r) {
                $labels['DEPARTMENT:'.$r->department_id] = [
                    (string) ($r->name ?: $r->code ?: 'دپارتمان'),
                    (string) ($r->code ?? ''),
                ];
            }
        }
        if (!empty($byType['COST_CENTER'])) {
            $rows = \App\Modules\Organization\Models\CostCenter::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('cost_center_id', array_unique($byType['COST_CENTER']))
                ->get(['cost_center_id', 'code', 'name']);
            foreach ($rows as $r) {
                $labels['COST_CENTER:'.$r->cost_center_id] = [
                    (string) ($r->name ?: $r->code ?: 'مرکز هزینه'),
                    (string) ($r->code ?? ''),
                ];
            }
        }

        foreach ($nodes as $n) {
            $key = strtoupper((string) $n->entity_type).':'.(string) $n->entity_id;
            $pair = $labels[$key] ?? null;
            $n->setAttribute('entity_label', $pair[0] ?? null);
            $n->setAttribute('entity_code', $pair[1] ?? null);
        }

        return $nodes;
    }

    public function addNode(
        string $hierarchyId,
        string $entityType,
        string $entityId,
        ?string $parentNodeId = null,
        int $sortOrder = 0,
        string $nodeOrigin = OrgHierarchyNode::ORIGIN_MANUAL,
    ): OrgHierarchyNode {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $entityType = strtoupper(trim($entityType));
        $nodeOrigin = strtoupper(trim($nodeOrigin));
        if (!in_array($nodeOrigin, OrgHierarchyNode::ORIGINS, true)) {
            $nodeOrigin = OrgHierarchyNode::ORIGIN_MANUAL;
        }

        if (!in_array($entityType, OrgHierarchyNode::ENTITY_TYPES, true)) {
            throw new \Exception('نوع موجودیت گره نامعتبر است.');
        }

        $hierarchy = OrgHierarchy::where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchyId)
            ->firstOrFail();

        if ($hierarchy->is_system && $nodeOrigin === OrgHierarchyNode::ORIGIN_MANUAL) {
            throw new \Exception(
                'افزودن گره دستی روی درخت سیستمی مجاز نیست. درخت‌های SYS از روی شرکت/شعبه/واحد کسب‌وکار همگام می‌شوند. برای ساختار سفارشی یک سلسله‌مراتب CUSTOM بسازید.'
            );
        }

        $this->assertEntityAllowedForPurpose($hierarchy->purpose, $entityType);
        $this->assertEntityExistsInTenant((string) $tenantId, $entityType, $entityId);

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
            'node_origin'    => $nodeOrigin,
            'sort_order'     => $sortOrder,
            'is_active'      => true,
            'row_version'    => 1,
        ]);
    }

    public function updateNode(
        string $nodeId,
        ?string $parentNodeId = null,
        ?int $sortOrder = null,
        bool $clearParent = false,
    ): OrgHierarchyNode {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $node = OrgHierarchyNode::where('tenant_id', $tenantId)->where('node_id', $nodeId)->firstOrFail();
        $hierarchy = OrgHierarchy::where('tenant_id', $tenantId)->where('hierarchy_id', $node->hierarchy_id)->firstOrFail();

        if ($hierarchy->is_system && ($node->node_origin ?? OrgHierarchyNode::ORIGIN_SYSTEM) === OrgHierarchyNode::ORIGIN_SYSTEM) {
            throw new \Exception('گره‌های سیستمی را نمی‌توان جابه‌جا کرد. ساختار را از شرکت/شعبه تغییر دهید.');
        }

        if ($clearParent) {
            $this->assertRootRules($hierarchy->purpose, (string) $node->entity_type);
            $node->parent_node_id = null;
        } elseif ($parentNodeId !== null) {
            if ($parentNodeId === $nodeId) {
                throw new \Exception('گره نمی‌تواند والد خودش باشد.');
            }
            $desc = $this->collectDescendantIds($tenantId, $node->hierarchy_id, $nodeId);
            if (in_array($parentNodeId, $desc, true)) {
                throw new \Exception('نمی‌توان گره را زیر یکی از نوادگان خودش قرار داد (حلقه).');
            }
            $parent = OrgHierarchyNode::where('tenant_id', $tenantId)
                ->where('node_id', $parentNodeId)
                ->where('hierarchy_id', $node->hierarchy_id)
                ->first();
            if (!$parent) {
                throw new \Exception('گره والد یافت نشد.');
            }
            if ($parent->is_active === false) {
                throw new \Exception('والد غیرفعال است.');
            }
            $this->assertParentChildRules($hierarchy->purpose, (string) $node->entity_type, $parent);
            $node->parent_node_id = $parentNodeId;
        }

        if ($sortOrder !== null) {
            $node->sort_order = max(0, $sortOrder);
        }

        $node->row_version = (int) $node->row_version + 1;
        $node->save();

        return $node->fresh();
    }

    public function reorderNodes(string $hierarchyId, array $items): int
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $hierarchy = OrgHierarchy::where('tenant_id', $tenantId)->where('hierarchy_id', $hierarchyId)->firstOrFail();

        if ($hierarchy->is_system) {
            throw new \Exception('ترتیب گره‌های درخت سیستمی از همگام‌سازی سازمان می‌آید و دستی قابل تغییر نیست.');
        }

        $count = 0;
        foreach ($items as $item) {
            $id = (string) ($item['node_id'] ?? '');
            $order = (int) ($item['sort_order'] ?? 0);
            if ($id === '') {
                continue;
            }
            $n = OrgHierarchyNode::where('tenant_id', $tenantId)
                ->where('hierarchy_id', $hierarchyId)
                ->where('node_id', $id)
                ->first();
            if ($n) {
                $n->sort_order = max(0, $order);
                $n->row_version = (int) $n->row_version + 1;
                $n->save();
                $count++;
            }
        }

        return $count;
    }

    public function softDeleteNode(string $nodeId): int
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $node = OrgHierarchyNode::where('tenant_id', $tenantId)
            ->where('node_id', $nodeId)
            ->firstOrFail();

        $hierarchy = OrgHierarchy::where('tenant_id', $tenantId)
            ->where('hierarchy_id', $node->hierarchy_id)
            ->first();

        if ($hierarchy && $hierarchy->is_system
            && ($node->node_origin ?? OrgHierarchyNode::ORIGIN_SYSTEM) === OrgHierarchyNode::ORIGIN_SYSTEM) {
            throw new \Exception('گره سیستمی قابل حذف دستی نیست.');
        }

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

        $hierarchy = OrgHierarchy::where('tenant_id', $tenantId)
            ->where('hierarchy_id', $node->hierarchy_id)
            ->first();
        if ($hierarchy && $hierarchy->is_system
            && ($node->node_origin ?? OrgHierarchyNode::ORIGIN_SYSTEM) === OrgHierarchyNode::ORIGIN_SYSTEM) {
            throw new \Exception('وضعیت گره سیستمی از همگام‌سازی سازمان می‌آید و دستی قابل تغییر نیست.');
        }

        if ($active && $node->is_active !== false) {
            throw new \Exception('این گره از قبل فعال است.');
        }
        if (!$active && $node->is_active === false) {
            throw new \Exception('این گره از قبل غیرفعال است.');
        }

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

        if (!$active) {
            $desc = $this->collectDescendantIds($tenantId, $node->hierarchy_id, $nodeId);
            if ($desc !== []) {
                OrgHierarchyNode::where('tenant_id', $tenantId)
                    ->whereIn('node_id', $desc)
                    ->update([
                        'is_active'   => false,
                        'row_version' => DB::raw('row_version + 1'),
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

        if ($h->is_system) {
            throw new \Exception('درخت سیستمی (SYS-*) قابل حذف نیست.');
        }

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

        if ($h->is_system && !$active) {
            throw new \Exception('غیرفعال‌سازی درخت سیستمی مجاز نیست.');
        }

        if ($active && $h->is_active !== false) {
            throw new \Exception('این درخت از قبل فعال است.');
        }
        if (!$active && $h->is_active === false) {
            throw new \Exception('این درخت از قبل غیرفعال است.');
        }

        $h->is_active = $active;
        $h->row_version = (int) $h->row_version + 1;
        $h->save();

        return $h->fresh();
    }

    public function bulkSetNodesActive(array $nodeIds, bool $active): int
    {
        $count = 0;
        foreach ($nodeIds as $id) {
            try {
                $this->setNodeActive((string) $id, $active);
                $count++;
            } catch (\Throwable) {
            }
        }
        return $count;
    }

    public function bulkSoftDeleteNodes(array $nodeIds): int
    {
        $count = 0;
        foreach ($nodeIds as $id) {
            try {
                $count += $this->softDeleteNode((string) $id);
            } catch (\Throwable) {
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

    protected function assertEntityExistsInTenant(string $tenantId, string $entityType, string $entityId): void
    {
        $entityType = strtoupper($entityType);
        $ok = false;

        switch ($entityType) {
            case 'COMPANY':
                $ok = \App\Modules\Organization\Models\Company::query()
                    ->where('tenant_id', $tenantId)
                    ->where('company_id', $entityId)
                    ->whereNull('deleted_at')
                    ->exists();
                break;
            case 'BRANCH':
                $ok = \App\Modules\Organization\Models\Branch::query()
                    ->where('tenant_id', $tenantId)
                    ->where('branch_id', $entityId)
                    ->whereNull('deleted_at')
                    ->exists();
                break;
            case 'BUSINESS_UNIT':
                $ok = \App\Modules\Organization\Models\BusinessUnit::query()
                    ->where('tenant_id', $tenantId)
                    ->where('business_unit_id', $entityId)
                    ->whereNull('deleted_at')
                    ->exists();
                break;
            case 'DEPARTMENT':
                $ok = \App\Modules\Organization\Models\Department::query()
                    ->where('tenant_id', $tenantId)
                    ->where('department_id', $entityId)
                    ->whereNull('deleted_at')
                    ->exists();
                break;
            case 'COST_CENTER':
                $ok = \App\Modules\Organization\Models\CostCenter::query()
                    ->where('tenant_id', $tenantId)
                    ->where('cost_center_id', $entityId)
                    ->whereNull('deleted_at')
                    ->exists();
                break;
            default:
                throw new \Exception('نوع موجودیت گره نامعتبر است.');
        }

        if (!$ok) {
            throw new \Exception(
                'موجودیت انتخاب‌شده در این مستأجر یافت نشد یا حذف شده است. فقط از شرکت/شعبه/واحد فعال همین سازمان استفاده کنید.'
            );
        }
    }

    protected function assertRootRules(string $purpose, string $entityType): void
    {
        $purpose = strtoupper($purpose);
        if (in_array($purpose, ['LEGAL', 'TAX', 'ESTABLISHMENT'], true) && $entityType !== 'COMPANY') {
            throw new \Exception('ریشهٔ این نوع درخت باید شرکت باشد.');
        }
    }

    protected function assertParentChildRules(string $purpose, string $childType, OrgHierarchyNode $parent): void
    {
        $purpose = strtoupper($purpose);
        $parentType = strtoupper((string) $parent->entity_type);

        if ($purpose === 'LEGAL' || $purpose === 'TAX') {
            if ($childType !== 'COMPANY' || $parentType !== 'COMPANY') {
                throw new \Exception('در درخت حقوقی/مالیاتی فقط شرکت زیر شرکت مجاز است.');
            }
            return;
        }

        if ($purpose === 'ESTABLISHMENT') {
            if ($childType === 'BRANCH' && $parentType !== 'COMPANY') {
                throw new \Exception('شعبه باید زیر شرکت باشد.');
            }
            if ($childType === 'COMPANY' && $parentType !== 'COMPANY') {
                throw new \Exception('شرکت فرزند باید زیر شرکت والد باشد.');
            }
            return;
        }
    }

    protected function collectDescendantIds(string $tenantId, string $hierarchyId, string $rootId): array
    {
        $all = OrgHierarchyNode::where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchyId)
            ->get(['node_id', 'parent_node_id']);

        $byParent = [];
        foreach ($all as $n) {
            $p = $n->parent_node_id ?? '';
            $byParent[$p][] = (string) $n->node_id;
        }

        $out = [];
        $stack = [$rootId];
        while ($stack) {
            $cur = array_pop($stack);
            foreach ($byParent[$cur] ?? [] as $cid) {
                $out[] = $cid;
                $stack[] = $cid;
            }
        }

        return $out;
    }

    protected function restoreAncestors(string $tenantId, OrgHierarchyNode $node): void
    {
        $pid = $node->parent_node_id;
        while ($pid) {
            $p = OrgHierarchyNode::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('node_id', $pid)
                ->first();
            if (!$p) {
                break;
            }
            if ($p->trashed()) {
                $p->restore();
            }
            $pid = $p->parent_node_id;
        }
    }
}
