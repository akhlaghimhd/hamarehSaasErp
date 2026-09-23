<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Services\CompanyOfficerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyOfficerController extends Controller
{
    public function __construct(
        private readonly CompanyOfficerService $service
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
            'role_code'             => 'required|string|max:50',
            'full_name'             => 'required|string|max:200',
            'role_title'            => 'nullable|string|max:150',
            'person_user_id'        => 'nullable|uuid',
            'national_id'           => 'nullable|string|max:50',
            'mandate_from'          => 'nullable|date',
            'mandate_to'            => 'nullable|date',
            'has_signing_authority' => 'sometimes|boolean',
            'mandate_notes'         => 'nullable|string|max:500',
            'is_active'             => 'sometimes|boolean',
        ]);

        $data['company_id'] = $company;
        $row = $this->service->create($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Officer created.',
            'data'    => $row,
        ], 201);
    }

    public function destroy(string $officer): JsonResponse
    {
        $this->service->softDelete($officer);

        return response()->json([
            'status'  => 'success',
            'message' => 'Officer deleted.',
        ]);
    }
}
