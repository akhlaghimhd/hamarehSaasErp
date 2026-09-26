<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Services\HierarchySyncService;
use App\Modules\Organization\Services\OrgHierarchyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrgHierarchyController extends Controller
{
    public function __construct(
        protected OrgHierarchyService $service,
        protected HierarchySyncService $syncService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $membership = strtolower((string) $request->query('membership', 'active'));
        $onlyTrashed = in_array($membership, ['deleted', 'trashed'], true);

        return response()->json([
            'status' => 'success',
            'data'   => $this->service->listHierarchies($onlyTrashed),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'       => 'required|string|max:50',
            'name'       => 'required|string|max:150',
            'purpose'    => 'required|string|max:30',
            'valid_from' => 'nullable|date',
            'valid_to'   => 'nullable|date',
        ]);

        $hier = $this->service->createHierarchy(
            $data['code'],
            $data['name'],
            $data['purpose'],
            $data['valid_from'] ?? null,
            $data['valid_to'] ?? null,
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Hierarchy created.',
            'data'    => $hier,
        ], 201);
    }

    public function destroy(string $hierarchy): JsonResponse
    {
        $this->service->softDeleteHierarchy($hierarchy);

        return response()->json([
            'status'  => 'success',
            'message' => 'Hierarchy soft-deleted (nodes included).',
        ]);
    }

    public function restore(string $hierarchy): JsonResponse
    {
        $h = $this->service->restoreHierarchy($hierarchy);

        return response()->json([
            'status'  => 'success',
            'message' => 'Hierarchy restored.',
            'data'    => $h,
        ]);
    }

    public function setActive(string $hierarchy, Request $request): JsonResponse
    {
        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $h = $this->service->setHierarchyActive($hierarchy, (bool) $data['is_active']);

        return response()->json([
            'status'  => 'success',
            'message' => $data['is_active'] ? 'Hierarchy activated.' : 'Hierarchy deactivated.',
            'data'    => $h,
        ]);
    }

    public function nodes(string $hierarchy, Request $request): JsonResponse
    {
        $membership = strtolower((string) $request->query('membership', 'active'));
        $onlyTrashed = in_array($membership, ['deleted', 'trashed'], true);

        $nodes = $this->service->listNodes($hierarchy, $onlyTrashed);

        return response()->json([
            'status' => 'success',
            'data'   => $nodes,
        ]);
    }

    public function addNode(string $hierarchy, Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type'    => 'required|string|max:30',
            'entity_id'      => 'required|uuid',
            'parent_node_id' => 'nullable|uuid',
            'sort_order'     => 'sometimes|integer|min:0',
        ]);

        try {
            $node = $this->service->addNode(
                $hierarchy,
                $data['entity_type'],
                $data['entity_id'],
                $data['parent_node_id'] ?? null,
                (int) ($data['sort_order'] ?? 0),
            );
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Node added.',
            'data'    => $node,
        ], 201);
    }

    public function destroyNode(string $node): JsonResponse
    {
        try {
            $count = $this->service->softDeleteNode($node);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => "Node soft-deleted (including {$count} row(s) with descendants).",
            'data'    => ['deleted_count' => $count],
        ]);
    }

    public function restoreNode(string $node): JsonResponse
    {
        try {
            $n = $this->service->restoreNode($node);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Node restored (ancestors restored if needed).',
            'data'    => $n,
        ]);
    }

    public function setNodeActive(string $node, Request $request): JsonResponse
    {
        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        try {
            $n = $this->service->setNodeActive($node, (bool) $data['is_active']);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => $data['is_active'] ? 'Node activated.' : 'Node deactivated (descendants too).',
            'data'    => $n,
        ]);
    }

    public function bulkNodes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'node_ids'   => 'required|array|min:1',
            'node_ids.*' => 'uuid',
            'action'     => 'required|in:activate,deactivate,delete',
        ]);

        $ids = $data['node_ids'];
        $action = $data['action'];

        $affected = match ($action) {
            'activate'   => $this->service->bulkSetNodesActive($ids, true),
            'deactivate' => $this->service->bulkSetNodesActive($ids, false),
            'delete'     => $this->service->bulkSoftDeleteNodes($ids),
        };

        return response()->json([
            'status'  => 'success',
            'message' => 'Bulk action applied.',
            'data'    => ['affected' => $affected, 'action' => $action],
        ]);
    }

    public function health(): JsonResponse
    {
        $report = $this->syncService->health();

        return response()->json([
            'status' => 'success',
            'data'   => $report,
        ]);
    }

    public function rebuild(): JsonResponse
    {
        $rebuild = $this->syncService->rebuildSystemTreesForTenant();
        $structural = $this->syncService->ensureStructuralTrees();
        $health = $this->syncService->health();

        return response()->json([
            'status'  => 'success',
            'message' => 'System hierarchies rebuilt from organization entities.',
            'data'    => [
                'rebuild'    => $rebuild,
                'structural' => $structural,
                'health'     => $health,
            ],
        ]);
    }
}
