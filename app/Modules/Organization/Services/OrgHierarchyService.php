<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\OrgHierarchy;
use App\Modules\Organization\Models\OrgHierarchyNode;
use App\Base\Context\TenantContext;
use Illuminate\Support\Str;

class OrgHierarchyService
{
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

        OrgHierarchy::where('tenant_id', $tenantId)->where('hierarchy_id', $hierarchyId)->firstOrFail();

        if ($parentNodeId) {
            $parent = OrgHierarchyNode::where('tenant_id', $tenantId)
                ->where('node_id', $parentNodeId)
                ->where('hierarchy_id', $hierarchyId)
                ->first();
            if (!$parent) {
                throw new \Exception('گره والد در این سلسله‌مراتب یافت نشد.');
            }
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

    public function listHierarchies()
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        return OrgHierarchy::where('tenant_id', $tenantId)
            ->orderBy('purpose')
            ->orderBy('code')
            ->get();
    }
}
