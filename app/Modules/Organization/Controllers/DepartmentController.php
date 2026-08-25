<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Requests\CreateDepartmentRequest;
use App\Modules\Organization\DTOs\CreateDepartmentDTO;
use App\Modules\Organization\Services\DepartmentService;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentService $departmentService
    ) {
    }

    public function index(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $tenantId = $this->getCurrentTenantId();
        $companyId = $this->request()->route('company');

        $departments = $this->departmentService->getDepartmentsByCompany($companyId, $user, $tenantId);

        return response()->json([
            'status'  => 'success',
            'data'    => $departments,
        ]);
    }

    public function store(CreateDepartmentRequest $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $dto = CreateDepartmentDTO::fromRequest($request->validated());
        $companyId = $this->request()->route('company');

        $department = $this->departmentService->createDepartment($dto, $companyId, $user);

        return response()->json([
            'status'  => 'success',
            'message' => 'Department created successfully.',
            'data'    => $department,
        ], 201);
    }

    public function show(string $departmentId): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $tenantId = $this->getCurrentTenantId();
        $companyId = $this->request()->route('company');

        $department = $this->departmentService->getDepartmentById($departmentId, $companyId, $user, $tenantId);

        return response()->json([
            'status'  => 'success',
            'data'    => $department,
        ]);
    }

    public function update(string $departmentId, UpdateDepartmentRequest $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $dto = UpdateDepartmentDTO::fromRequest($request->validated());
        $companyId = $this->request()->route('company');

        $department = $this->departmentService->updateDepartment($departmentId, $dto, $companyId, $user);

        return response()->json([
            'status'  => 'success',
            'message' => 'Department updated successfully.',
            'data'    => $department,
        ]);
    }

    public function destroy(string $departmentId): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $tenantId = $this->getCurrentTenantId();
        $companyId = $this->request()->route('company');

        $this->departmentService->deleteDepartment($departmentId, $companyId, $user, $tenantId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Department deleted successfully.',
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