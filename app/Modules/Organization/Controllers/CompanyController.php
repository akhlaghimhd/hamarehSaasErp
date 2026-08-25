<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Requests\CreateCompanyRequest;
use App\Modules\Organization\DTOs\CreateCompanyDTO;
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
        $user = $this->getAuthenticatedUser();
        $tenantId = $this->getCurrentTenantId();

        $companies = $this->companyService->getCompaniesByUser($user, $tenantId);

        return response()->json([
            'status'  => 'success',
            'data'    => $companies,
        ]);
    }

    public function store(CreateCompanyRequest $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $dto = CreateCompanyDTO::fromRequest($request->validated());

        $company = $this->companyService->createCompany($dto, $user);

        return response()->json([
            'status'  => 'success',
            'message' => 'Company created successfully.',
            'data'    => $company,
        ], 201);
    }

    public function show(string $companyId): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $tenantId = $this->getCurrentTenantId();

        $company = $this->companyService->getCompanyById($companyId, $user, $tenantId);

        return response()->json([
            'status'  => 'success',
            'data'    => $company,
        ]);
    }

    public function update(string $companyId, UpdateCompanyRequest $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $dto = UpdateCompanyDTO::fromRequest($request->validated());

        $company = $this->companyService->updateCompany($companyId, $dto, $user);

        return response()->json([
            'status'  => 'success',
            'message' => 'Company updated successfully.',
            'data'    => $company,
        ]);
    }

    public function destroy(string $companyId): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $tenantId = $this->getCurrentTenantId();

        $this->companyService->deleteCompany($companyId, $user, $tenantId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Company deleted successfully.',
        ]);
    }

    private function getAuthenticatedUser()
    {
        return $this->request()->user();
    }

    private function getCurrentTenantId()
    {
        return $this->request()->headers->get('X-Tenant-ID');
    }
}