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
            'owner_kind'          => 'nullable|string|in:COMPANY,EXTERNAL_PERSON,EXTERNAL_ORG',
            'owner_company_id'    => 'nullable|uuid',
            'owner_display_name'  => 'nullable|string|max:200',
            'owner_identifier'    => 'nullable|string|max:50',
            'ownership_percent'   => 'required|numeric|gt:0|lte:100',
            'relation_type'       => 'nullable|string|max:30',
            'valid_from'          => 'nullable|date',
            'valid_to'            => 'nullable|date',
        ]);

        $kind = strtoupper($data['owner_kind'] ?? CompanyOwnershipService::KIND_COMPANY);

        $row = $this->service->create(
            $company,
            (float) $data['ownership_percent'],
            $data['relation_type'] ?? 'EQUITY',
            $kind,
            $data['owner_company_id'] ?? null,
            $data['owner_display_name'] ?? null,
            $data['owner_identifier'] ?? null,
            $data['valid_from'] ?? null,
            $data['valid_to'] ?? null,
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Ownership created.',
            'data'    => $row,
        ], 201);
    }

    public function update(string $ownership, Request $request): JsonResponse
    {
        $data = $request->validate([
            'owner_kind'          => 'nullable|string|in:COMPANY,EXTERNAL_PERSON,EXTERNAL_ORG',
            'owner_company_id'    => 'nullable|uuid',
            'owner_display_name'  => 'nullable|string|max:200',
            'owner_identifier'    => 'nullable|string|max:50',
            'ownership_percent'   => 'required|numeric|gt:0|lte:100',
            'relation_type'       => 'nullable|string|max:30',
            'valid_from'          => 'nullable|date',
            'valid_to'            => 'nullable|date',
        ]);

        $kind = strtoupper($data['owner_kind'] ?? CompanyOwnershipService::KIND_COMPANY);

        $row = $this->service->update(
            $ownership,
            (float) $data['ownership_percent'],
            $data['relation_type'] ?? 'EQUITY',
            $kind,
            $data['owner_company_id'] ?? null,
            $data['owner_display_name'] ?? null,
            $data['owner_identifier'] ?? null,
            $data['valid_from'] ?? null,
            $data['valid_to'] ?? null,
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Ownership updated.',
            'data'    => $row,
        ]);
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
