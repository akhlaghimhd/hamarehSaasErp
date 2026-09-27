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

    /** Route param is {department} — must match argument name. */
    public function show(string $department): JsonResponse
    {
        $found = $this->departmentService->getDepartmentById($department);

        if (!$found) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Department not found or access denied.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $found,
        ]);
    }

    public function update(string $department, UpdateDepartmentRequest $request): JsonResponse
    {
        $dto = UpdateDepartmentDTO::fromRequest($request->validated());
        $updated = $this->departmentService->updateDepartment($department, $dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'Department updated successfully.',
            'data'    => $updated,
        ]);
    }

    public function destroy(string $department): JsonResponse
    {
        $this->departmentService->deleteDepartment($department);

        return response()->json([
            'status'  => 'success',
            'message' => 'Department deleted successfully.',
        ]);
    }

    public function restore(string $department): JsonResponse
    {
        $restored = $this->departmentService->restoreDepartment($department);

        return response()->json([
            'status'  => 'success',
            'message' => 'Department restored successfully.',
            'data'    => $restored,
        ]);
    }
}
