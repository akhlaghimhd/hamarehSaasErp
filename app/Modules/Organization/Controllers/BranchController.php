<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Requests\CreateBranchRequest;
use App\Modules\Organization\DTOs\CreateBranchDTO;
use App\Modules\Organization\Services\BranchService;
use Illuminate\Http\JsonResponse;

class BranchController extends Controller
{
    public function __construct(
        private readonly BranchService $branchService
    ) {
    }

    public function index(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $tenantId = $this->getCurrentTenantId();
        $companyId = $this->request()->route('company');

        $branches = $this->branchService->getBranchesByCompany($companyId, $user, $tenantId);

        return response()->json([
            'status'  => 'success',
            'data'    => $branches,
        ]);
    }

    public function store(CreateBranchRequest $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $dto = CreateBranchDTO::fromRequest($request->validated());
        $companyId = $this->request()->route('company');

        $branch = $this->branchService->createBranch($dto, $companyId, $user);

        return response()->json([
            'status'  => 'success',
            'message' => 'Branch created successfully.',
            'data'    => $branch,
        ], 201);
    }

    public function show(string $branchId): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $tenantId = $this->getCurrentTenantId();
        $companyId = $this->request()->route('company');

        $branch = $this->branchService->getBranchById($branchId, $companyId, $user, $tenantId);

        return response()->json([
            'status'  => 'success',
            'data'    => $branch,
        ]);
    }

    public function update(string $branchId, UpdateBranchRequest $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $dto = UpdateBranchDTO::fromRequest($request->validated());
        $companyId = $this->request()->route('company');

        $branch = $this->branchService->updateBranch($branchId, $dto, $companyId, $user);

        return response()->json([
            'status'  => 'success',
            'message' => 'Branch updated successfully.',
            'data'    => $branch,
        ]);
    }

    public function destroy(string $branchId): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $tenantId = $this->getCurrentTenantId();
        $companyId = $this->request()->route('company');

        $this->branchService->deleteBranch($branchId, $companyId, $user, $tenantId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Branch deleted successfully.',
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