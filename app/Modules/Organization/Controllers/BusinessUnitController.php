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

    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->listForTenant(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'        => 'required|string|max:50',
            'name'        => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
        ]);

        $bu = $this->service->create($data['code'], $data['name'], $data['description'] ?? null);

        return response()->json([
            'status'  => 'success',
            'message' => 'Business unit created.',
            'data'    => $bu,
        ], 201);
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
