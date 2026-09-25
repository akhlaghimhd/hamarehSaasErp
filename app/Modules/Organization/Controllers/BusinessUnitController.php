<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Services\BusinessUnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessUnitController extends Controller
{
    public function __construct(
        private readonly BusinessUnitService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $membership = $request->query('membership', 'active');
        $onlyTrashed = $membership === 'deleted';

        return response()->json([
            'status' => 'success',
            'data'   => $this->service->listForTenant($onlyTrashed),
        ]);
    }

    public function show(string $businessUnit): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->find($businessUnit),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'        => 'required|string|max:50',
            'name'        => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'sometimes|boolean',
        ]);

        $bu = $this->service->create(
            $data['code'],
            $data['name'],
            $data['description'] ?? null,
            (bool) ($data['is_active'] ?? true)
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Business unit created.',
            'data'    => $bu,
        ], 201);
    }

    public function update(string $businessUnit, Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'        => 'required|string|max:50',
            'name'        => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'sometimes|boolean',
        ]);

        $bu = $this->service->update(
            $businessUnit,
            $data['code'],
            $data['name'],
            $data['description'] ?? null,
            array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Business unit updated.',
            'data'    => $bu,
        ]);
    }

    public function destroy(string $businessUnit): JsonResponse
    {
        $this->service->softDelete($businessUnit);

        return response()->json([
            'status'  => 'success',
            'message' => 'Business unit deleted.',
        ]);
    }

    public function restore(string $businessUnit): JsonResponse
    {
        $bu = $this->service->restore($businessUnit);

        return response()->json([
            'status'  => 'success',
            'message' => 'Business unit restored.',
            'data'    => $bu,
        ]);
    }

    public function assignCompany(string $businessUnit, Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|uuid',
            'is_primary' => 'sometimes|boolean',
        ]);

        $row = $this->service->assignCompany(
            $businessUnit,
            $data['company_id'],
            (bool) ($data['is_primary'] ?? false)
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Company assigned to business unit.',
            'data'    => $row,
        ], 201);
    }
}
