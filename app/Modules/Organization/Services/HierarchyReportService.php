<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Contracts\HierarchyReportContract;
use App\Modules\Organization\Models\OrgHierarchy;
use Illuminate\Support\Facades\DB;

/**
 * ORG-W2-03 — Default implementation of HierarchyReportContract.
 */
class HierarchyReportService implements HierarchyReportContract
{
    public function activeHierarchy(string $tenantId, string $purpose): ?array
    {
        $purpose = strtoupper(trim($purpose));
        if (!in_array($purpose, OrgHierarchy::PURPOSES, true)) {
            return null;
        }

        $row = DB::table('erp_org_hierarchies')
            ->where('tenant_id', $tenantId)
            ->where('purpose', $purpose)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderByDesc('version')
            ->first([
                'hierarchy_id',
                'code',
                'name',
                'purpose',
                'version',
                'valid_from',
                'valid_to',
            ]);

        if (!$row) {
            return null;
        }

        return [
            'hierarchy_id' => (string) $row->hierarchy_id,
            'code'         => (string) $row->code,
            'name'         => (string) $row->name,
            'purpose'      => (string) $row->purpose,
            'version'      => (int) $row->version,
            'valid_from'   => $row->valid_from ? (string) $row->valid_from : null,
            'valid_to'     => $row->valid_to ? (string) $row->valid_to : null,
        ];
    }

    public function nodesByPurpose(string $tenantId, string $purpose): array
    {
        $hier = $this->activeHierarchy($tenantId, $purpose);
        if (!$hier) {
            return [];
        }

        return DB::table('erp_org_hierarchy_nodes')
            ->where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hier['hierarchy_id'])
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get([
                'node_id',
                'parent_node_id',
                'entity_type',
                'entity_id',
                'sort_order',
                'is_active',
            ])
            ->map(fn ($n) => [
                'node_id'        => (string) $n->node_id,
                'parent_node_id' => $n->parent_node_id ? (string) $n->parent_node_id : null,
                'entity_type'    => strtoupper((string) $n->entity_type),
                'entity_id'      => (string) $n->entity_id,
                'sort_order'     => (int) $n->sort_order,
                'is_active'      => (bool) $n->is_active,
            ])
            ->all();
    }

    public function treeByPurpose(string $tenantId, string $purpose): array
    {
        $nodes = $this->nodesByPurpose($tenantId, $purpose);
        if ($nodes === []) {
            return [];
        }

        $byParent = [];
        foreach ($nodes as $n) {
            $pid = $n['parent_node_id'] ?? '';
            $byParent[$pid][] = $n;
        }

        $build = function (string $parentId) use (&$build, $byParent): array {
            $children = [];
            foreach ($byParent[$parentId] ?? [] as $n) {
                $children[] = [
                    'node_id'     => $n['node_id'],
                    'entity_type' => $n['entity_type'],
                    'entity_id'   => $n['entity_id'],
                    'sort_order'  => $n['sort_order'],
                    'children'    => $build($n['node_id']),
                ];
            }

            return $children;
        };

        return $build('');
    }

    public function descendantEntityIds(
        string $tenantId,
        string $purpose,
        string $entityType,
        string $entityId
    ): array {
        $entityType = strtoupper($entityType);
        $nodes = $this->nodesByPurpose($tenantId, $purpose);
        if ($nodes === []) {
            return [$entityId];
        }

        $rootNodeId = null;
        foreach ($nodes as $n) {
            if ($n['entity_type'] === $entityType && $n['entity_id'] === $entityId) {
                $rootNodeId = $n['node_id'];
                break;
            }
        }

        if ($rootNodeId === null) {
            return [$entityId];
        }

        $childrenMap = [];
        foreach ($nodes as $n) {
            $pid = $n['parent_node_id'] ?? '';
            if ($pid === '') {
                continue;
            }
            $childrenMap[$pid][] = $n;
        }

        $result = [$entityId];
        $queue = [$rootNodeId];
        $visited = [$rootNodeId => true];
        $guard = 0;

        while ($queue !== [] && $guard < 5000) {
            $guard++;
            $current = array_shift($queue);
            foreach ($childrenMap[$current] ?? [] as $child) {
                $cid = $child['node_id'];
                if (isset($visited[$cid])) {
                    continue;
                }
                $visited[$cid] = true;
                $queue[] = $cid;
                if ($child['entity_type'] === $entityType) {
                    $result[] = $child['entity_id'];
                }
            }
        }

        return array_values(array_unique($result));
    }
}
