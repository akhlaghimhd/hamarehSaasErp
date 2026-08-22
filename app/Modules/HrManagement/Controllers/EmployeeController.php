<?php

namespace App\Modules\HrManagement\Controllers;

use App\Base\Controller;
use App\Modules\HrManagement\Requests\CreateEmployeeRequest;
use App\Modules\HrManagement\DTOs\CreateEmployeeDTO;
use App\Modules\HrManagement\Services\EmployeeService;
use Illuminate\Http\JsonResponse;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService
    ) {}

    public function store(CreateEmployeeRequest $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID');

        $dto = CreateEmployeeDTO::fromArray($request->validated(), $tenantId);

        $employee = $this->employeeService->createEmployee($dto);

        return response()->json([
            'success' => true,
            'message' => 'اطلاعات پرسنل با موفقیت ثبت شد.',
            'data'    => $employee
        ], 201);
    }
}