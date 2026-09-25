<?php

namespace App\Modules\Organization\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\OrgHierarchy;
use App\Modules\Organization\Models\OrgHierarchyNode;
use App\Modules\Organization\Services\HierarchySyncService;
use App\Modules\Organization\Services\OrgHierarchyService;
use App\Base\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrgHierarchyController extends Controller
{
    public function __construct(
        protected OrgHierarchyService $service,
        protected HierarchySyncService $syncService,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->listHierarchies(),
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

    public function nodes(string $hierarchy): JsonResponse
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        OrgHierarchy::where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchy)
            ->firstOrFail();

        $nodes = OrgHierarchyNode::where('tenant_id', $tenantId)
            ->where('hierarchy_id', $hierarchy)
            ->orderBy('sort_order')
            ->get();

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

        $node = $this->service->addNode(
            $hierarchy,
            $data['entity_type'],
            $data['entity_id'],
            $data['parent_node_id'] ?? null,
            (int) ($data['sort_order'] ?? 0),
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Node added.',
            'data'    => $node,
        ], 201);
    }

    /** P3 — hierarchy health for current tenant */
    public function health(): JsonResponse
    {
        $report = $this->syncService->health();

        return response()->json([
            'status' => 'success',
            'data'   => $report,
        ]);
    }

    /** P3 — rebuild system trees for current tenant */
    public function rebuild(): JsonResponse
    {
        $this->syncService->rebuildSystemTreesForTenant();
        $structural = $this->syncService->ensureStructuralTrees();
        $health = $this->syncService->health();

        return response()->json([
            'status'  => 'success',
            'message' => 'System hierarchies rebuilt.',
            'data'    => [
                'structural' => $structural,
                'health'     => $health,
            ],
        ]);
    }
}
