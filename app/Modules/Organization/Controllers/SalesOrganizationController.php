<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Services\SalesOrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesOrganizationController extends Controller
{
    public function __construct(
        private readonly SalesOrganizationService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $onlyTrashed = $request->query('membership') === 'deleted';

        return response()->json([
            'status' => 'success',
            'data'   => $this->service->listForTenant($onlyTrashed),
        ]);
    }

    public function show(string $salesOrg): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->find($salesOrg),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'        => 'required|string|max:50',
            'name'        => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'company_id'  => 'nullable|uuid',
            'is_active'   => 'sometimes|boolean',
        ]);

        $row = $this->service->create(
            $data['code'],
            $data['name'],
            $data['company_id'] ?? null,
            $data['description'] ?? null,
            (bool) ($data['is_active'] ?? true)
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Sales organization created.',
            'data'    => $row,
        ], 201);
    }

    public function update(string $salesOrg, Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'        => 'required|string|max:50',
            'name'        => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'company_id'  => 'nullable|uuid',
            'is_active'   => 'sometimes|boolean',
        ]);

        $row = $this->service->update(
            $salesOrg,
            $data['code'],
            $data['name'],
            $data['company_id'] ?? null,
            $data['description'] ?? null,
            array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Sales organization updated.',
            'data'    => $row,
        ]);
    }

    public function destroy(string $salesOrg): JsonResponse
    {
        $this->service->softDelete($salesOrg);

        return response()->json([
            'status'  => 'success',
            'message' => 'Sales organization deleted.',
        ]);
    }

    public function restore(string $salesOrg): JsonResponse
    {
        $row = $this->service->restore($salesOrg);

        return response()->json([
            'status'  => 'success',
            'message' => 'Sales organization restored.',
            'data'    => $row,
        ]);
    }

    public function assignments(string $salesOrg): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->listAssignments($salesOrg),
        ]);
    }

    public function assign(string $salesOrg, Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'nullable|uuid',
            'branch_id'  => 'nullable|uuid',
        ]);

        $row = $this->service->assign(
            $salesOrg,
            $data['company_id'] ?? null,
            $data['branch_id'] ?? null,
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Assignment created.',
            'data'    => $row,
        ], 201);
    }

    public function unassign(string $assignment): JsonResponse
    {
        $this->service->unassign($assignment);

        return response()->json([
            'status'  => 'success',
            'message' => 'Assignment removed.',
        ]);
    }
}
