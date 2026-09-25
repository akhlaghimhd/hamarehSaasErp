<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\OrgHierarchy;
use App\Modules\Organization\Models\OrgHierarchyNode;
use App\Base\Context\TenantContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Smart Hierarchy Product Law v1.0 — P1
 *
 * Derives system trees from source entities (company / branch).
 * Must never throw out to callers of CompanyService / BranchService;
 * callers wrap with try/catch as well. Point-sync only; idempotent upsert.
 */
class HierarchySyncService
{
    public const CODE_LEGAL = 'SYS-LEGAL';
    public const CODE_ESTABLISHMENT = 'SYS-ESTABLISHMENT';

    /**
     * Upsert company into LEGAL (parent from parent_company_id) and ESTABLISHMENT (company as structural root).
     */
    public function syncCompany(Company $company): void
    {
        $tenantId = (string) $company->tenant_id;
        if ($tenantId === '') {
            $tenantId = (string) (TenantContext::getInstance()->getTenantId() ?? '');
        }
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
            // Parent may not be synced yet — ensure parent node exists without forcing its parent chain fully
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
                        $parent->trashed() || $parent->is_active === false ? false : true
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
        $tenantId = (string) $branch->tenant_id;
        if ($tenantId === '') {
            $tenantId = (string) (TenantContext::getInstance()->getTenantId() ?? '');
        }
        if ($tenantId === '' || empty($branch->company_id)) {
            return;
        }

        $estId = $this->ensureSystemHierarchy(
            $tenantId,
            self::CODE_ESTABLISHMENT,
            'استقرار',
            OrgHierarchy::PURPOSE_ESTABLISHMENT
        );

        // Ensure company node exists under establishment
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
        $tenantId = TenantContext::getInstance()->getTenantId();
        if (!$tenantId) {
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

    /** Soft-delete / deactivate all nodes for an entity across system hierarchies. */
    public function deactivateEntityNodes(string $entityType, string $entityId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        if (!$tenantId) {
            return;
        }

        OrgHierarchyNode::where('tenant_id', $tenantId)
            ->where('entity_type', strtoupper($entityType))
            ->where('entity_id', $entityId)
            ->whereNull('deleted_at')
            ->update([
                'is_active'   => false,
                'updated_at'  => now(),
            ]);
    }

    public function reactivateEntityNodes(string $entityType, string $entityId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        if (!$tenantId) {
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
     * Rebuild system LEGAL + ESTABLISHMENT from current companies/branches (admin repair).
     */
    public function rebuildSystemTreesForTenant(?string $tenantId = null): void
    {
        $tenantId = $tenantId ?: TenantContext::getInstance()->getTenantId();
        if (!$tenantId) {
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

    /** Safe entry for services — never throws. */
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
