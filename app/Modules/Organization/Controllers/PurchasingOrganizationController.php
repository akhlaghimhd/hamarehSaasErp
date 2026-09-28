<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Services\PurchasingOrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchasingOrganizationController extends Controller
{
    public function __construct(
        private readonly PurchasingOrganizationService $service
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

    public function show(string $purchOrg): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->find($purchOrg),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'         => 'required|string|max:50',
            'name'         => 'required|string|max:200',
            'description'  => 'nullable|string|max:500',
            'company_id'   => 'nullable|uuid',
            'is_active'    => 'sometimes|boolean',
            'is_reference' => 'sometimes|boolean',
        ]);

        $row = $this->service->create(
            $data['code'],
            $data['name'],
            $data['company_id'] ?? null,
            $data['description'] ?? null,
            (bool) ($data['is_active'] ?? true),
            (bool) ($data['is_reference'] ?? false)
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Purchasing organization created.',
            'data'    => $row,
        ], 201);
    }

    public function update(string $purchOrg, Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'         => 'required|string|max:50',
            'name'         => 'required|string|max:200',
            'description'  => 'nullable|string|max:500',
            'company_id'   => 'nullable|uuid',
            'is_active'    => 'sometimes|boolean',
            'is_reference' => 'sometimes|boolean',
        ]);

        $row = $this->service->update(
            $purchOrg,
            $data['code'],
            $data['name'],
            $data['company_id'] ?? null,
            $data['description'] ?? null,
            array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            array_key_exists('is_reference', $data) ? (bool) $data['is_reference'] : null
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Purchasing organization updated.',
            'data'    => $row,
        ]);
    }

    public function destroy(string $purchOrg): JsonResponse
    {
        $this->service->softDelete($purchOrg);

        return response()->json([
            'status'  => 'success',
            'message' => 'Purchasing organization deleted.',
        ]);
    }

    public function restore(string $purchOrg): JsonResponse
    {
        $row = $this->service->restore($purchOrg);

        return response()->json([
            'status'  => 'success',
            'message' => 'Purchasing organization restored.',
            'data'    => $row,
        ]);
    }

    public function assignments(string $purchOrg): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->listAssignments($purchOrg),
        ]);
    }

    public function assign(string $purchOrg, Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'nullable|uuid',
            'branch_id'  => 'nullable|uuid',
        ]);

        $row = $this->service->assign(
            $purchOrg,
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
