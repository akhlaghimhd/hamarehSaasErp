<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Requests\CreateDepartmentRequest;
use App\Modules\Organization\Services\DepartmentService;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    public function __construct(private readonly DepartmentService $departmentService) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->departmentService->getAllDepartments()]);
    }

    public function store(CreateDepartmentRequest $request): JsonResponse
    {
        try {
            $department = $this->departmentService->createDepartment($request->toDTO());
            return response()->json(['message' => 'دپارتمان با موفقیت ثبت شد.', 'data' => $department], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
    public function update(\App\Modules\Organization\Requests\UpdateDepartmentRequest $request, string $id): JsonResponse
    {
        try {
            $department = $this->departmentService->updateDepartment($id, $request->toDTO());
            return response()->json(['message' => 'اطلاعات دپارتمان با موفقیت ویرایش شد.', 'data' => $department], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->departmentService->deleteDepartment($id);
            return response()->json(['message' => 'دپارتمان با موفقیت حذف شد.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}