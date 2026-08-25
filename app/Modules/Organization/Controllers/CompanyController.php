<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Requests\CreateCompanyRequest;
use App\Modules\Organization\Requests\UpdateCompanyRequest;
use App\Modules\Organization\DTOs\CreateCompanyDTO;
use App\Modules\Organization\DTOs\UpdateCompanyDTO;
use App\Modules\Organization\Services\CompanyService;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $companyService
    ) {
    }

    public function index(): JsonResponse
    {
        $companies = $this->companyService->getAllCompanies();

        return response()->json([
            'status' => 'success',
            'data'   => $companies,
        ]);
    }

    public function store(CreateCompanyRequest $request): JsonResponse
    {
        $dto = CreateCompanyDTO::fromRequest($request->validated());
        $company = $this->companyService->createCompany($dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'Company created successfully.',
            'data'    => $company,
        ], 201);
    }

    public function show(string $companyId): JsonResponse
    {
        // getAllCompanies already applies Scope; for single item we rely on service isolation
        $companies = $this->companyService->getAllCompanies();
        $company = $companies->firstWhere('company_id', $companyId);

        if (!$company) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Company not found or access denied.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $company,
        ]);
    }

    public function update(string $companyId, UpdateCompanyRequest $request): JsonResponse
    {
        $dto = UpdateCompanyDTO::fromRequest($request->validated());
        $company = $this->companyService->updateCompany($companyId, $dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'Company updated successfully.',
            'data'    => $company,
        ]);
    }

    public function destroy(string $companyId): JsonResponse
    {
        $this->companyService->deleteCompany($companyId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Company deleted successfully.',
        ]);
    }
}