<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Requests\CreateDepartmentRequest;
use App\Modules\Organization\Requests\UpdateDepartmentRequest;
use App\Modules\Organization\DTOs\CreateDepartmentDTO;
use App\Modules\Organization\DTOs\UpdateDepartmentDTO;
use App\Modules\Organization\Services\DepartmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentService $departmentService
    ) {
    }

    public function index(Request $request, ?string $company = null): JsonResponse
    {
        $companyId = $company ?? $request->route('company');
        $membership = strtolower((string) $request->query('membership', 'active'));
        $onlyTrashed = in_array($membership, ['deleted', 'trashed'], true);

        $departments = $this->departmentService->getAllDepartments(
            companyId: $companyId ? (string) $companyId : null,
            onlyTrashed: $onlyTrashed
        );

        return response()->json([
            'status' => 'success',
            'data'   => $departments,
        ]);
    }

    public function store(CreateDepartmentRequest $request): JsonResponse
    {
        $dto = CreateDepartmentDTO::fromRequest($request->validated());
        $department = $this->departmentService->createDepartment($dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'Department created successfully.',
            'data'    => $department,
        ], 201);
    }

    public function show(string $departmentId): JsonResponse
    {
        $departments = $this->departmentService->getAllDepartments();
        $department = $departments->firstWhere('department_id', $departmentId);

        if (!$department) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Department not found or access denied.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $department,
        ]);
    }

    public function update(string $departmentId, UpdateDepartmentRequest $request): JsonResponse
    {
        $dto = UpdateDepartmentDTO::fromRequest($request->validated());
        $department = $this->departmentService->updateDepartment($departmentId, $dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'Department updated successfully.',
            'data'    => $department,
        ]);
    }

    public function destroy(string $departmentId): JsonResponse
    {
        $this->departmentService->deleteDepartment($departmentId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Department deleted successfully.',
        ]);
    }

    public function restore(string $departmentId): JsonResponse
    {
        $department = $this->departmentService->restoreDepartment($departmentId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Department restored successfully.',
            'data'    => $department,
        ]);
    }
}
