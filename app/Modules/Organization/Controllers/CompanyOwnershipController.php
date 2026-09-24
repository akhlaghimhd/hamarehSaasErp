<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Services\CompanyOwnershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyOwnershipController extends Controller
{
    public function __construct(
        private readonly CompanyOwnershipService $service
    ) {
    }

    public function index(string $company): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->listForCompany($company),
        ]);
    }

    public function store(string $company, Request $request): JsonResponse
    {
        $data = $request->validate([
            'owner_company_id'  => 'required|uuid',
            'ownership_percent' => 'required|numeric|gt:0|lte:100',
            'relation_type'     => 'nullable|string|max:30',
            'valid_from'        => 'nullable|date',
            'valid_to'          => 'nullable|date',
        ]);

        $row = $this->service->create(
            $company,
            $data['owner_company_id'],
            (float) $data['ownership_percent'],
            $data['relation_type'] ?? 'EQUITY',
            $data['valid_from'] ?? null,
            $data['valid_to'] ?? null,
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Ownership created.',
            'data'    => $row,
        ], 201);
    }

    public function destroy(string $ownership): JsonResponse
    {
        $this->service->softDelete($ownership);

        return response()->json([
            'status'  => 'success',
            'message' => 'Ownership deleted.',
        ]);
    }
}
