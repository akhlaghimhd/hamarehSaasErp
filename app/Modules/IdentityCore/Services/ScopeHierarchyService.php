<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\Organization\Models\OrgHierarchy;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ID-W2-04 — Resolve scope references against org hierarchy purpose trees.
 *
 * When a scope has hierarchy_purpose + include_subtree=true, the effective
 * entity set is the node matching reference_id plus all descendant entities
 * of the same entity_type (or any) in that purpose tree.
 */
class ScopeHierarchyService
{
    /**
     * Validate purpose code against known hierarchy purposes.
     */
    public function assertValidPurpose(?string $purpose): void
    {
        if ($purpose === null || $purpose === '') {
            return;
        }

        $purpose = strtoupper(trim($purpose));
        if (!in_array($purpose, OrgHierarchy::PURPOSES, true)) {
            throw new HttpException(
                422,
                'hierarchy_purpose نامعتبر است. مقادیر مجاز: '.implode(', ', OrgHierarchy::PURPOSES)
            );
        }
    }

    /**
     * Expand a single scope reference into effective entity ids.
     *
     * @return list<string>
     */
    public function expandReference(
        string $tenantId,
        string $scopeType,
        ?string $referenceId,
        ?string $hierarchyPurpose,
        bool $includeSubtree
    ): array {
        if ($referenceId === null || $referenceId === '') {
            return [];
        }

        $ids = [$referenceId];

        if (!$includeSubtree || $hierarchyPurpose === null || $hierarchyPurpose === '') {
            return $ids;
        }

        $purpose = strtoupper($hierarchyPurpose);
        $entityType = strtoupper($scopeType);

        // Find hierarchy for purpose (prefer active)
        $hierarchy = DB::table('erp_org_hierarchies')
            ->where('tenant_id', $tenantId)
            ->where('purpose', $purpose)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderByDesc('version')
            ->first(['hierarchy_id']);

        if (!$hierarchy) {
            return $ids;
        }

        $rootNode = DB::table('erp_org_hierarchy_nodes')
            ->where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchy->hierarchy_id)
            ->where('entity_type', $entityType)
            ->where('entity_id', $referenceId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first(['node_id']);

        if (!$rootNode) {
            return $ids;
        }

        $descendantIds = $this->collectDescendantEntityIds(
            $tenantId,
            (string) $hierarchy->hierarchy_id,
            (string) $rootNode->node_id,
            $entityType
        );

        return array_values(array_unique(array_merge($ids, $descendantIds)));
    }

    /**
     * @param  list<array{scope_type:string,reference_id:?string,hierarchy_purpose:?string,include_subtree?:bool}>  $scopes
     * @return array<string, list<string>> map scope_type => entity ids
     */
    public function expandScopes(string $tenantId, array $scopes): array
    {
        $byType = [];

        foreach ($scopes as $scope) {
            $type = strtoupper((string) ($scope['scope_type'] ?? ''));
            if ($type === '') {
                continue;
            }

            $expanded = $this->expandReference(
                $tenantId,
                $type,
                $scope['reference_id'] ?? null,
                $scope['hierarchy_purpose'] ?? null,
                (bool) ($scope['include_subtree'] ?? false)
            );

            if (!isset($byType[$type])) {
                $byType[$type] = [];
            }
            $byType[$type] = array_values(array_unique(array_merge($byType[$type], $expanded)));
        }

        return $byType;
    }

    /**
     * BFS over parent_node_id children; collect entity_id of matching entity_type.
     *
     * @return list<string>
     */
    private function collectDescendantEntityIds(
        string $tenantId,
        string $hierarchyId,
        string $rootNodeId,
        string $entityType
    ): array {
        $allNodes = DB::table('erp_org_hierarchy_nodes')
            ->where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchyId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get(['node_id', 'parent_node_id', 'entity_type', 'entity_id']);

        $childrenMap = [];
        foreach ($allNodes as $n) {
            $pid = $n->parent_node_id ?? '';
            if ($pid === '') {
                continue;
            }
            $childrenMap[$pid][] = $n;
        }

        $result = [];
        $queue = [$rootNodeId];
        $visited = [$rootNodeId => true];
        $guard = 0;

        while ($queue !== [] && $guard < 5000) {
            $guard++;
            $current = array_shift($queue);
            foreach ($childrenMap[$current] ?? [] as $child) {
                $cid = (string) $child->node_id;
                if (isset($visited[$cid])) {
                    continue;
                }
                $visited[$cid] = true;
                $queue[] = $cid;

                if (strtoupper((string) $child->entity_type) === $entityType) {
                    $result[] = (string) $child->entity_id;
                }
            }
        }

        return $result;
    }
}
