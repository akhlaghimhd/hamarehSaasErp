<?php

namespace App\Modules\HrManagement\Controllers;

use App\Base\Controller;
use App\Modules\HrManagement\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $service
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->list()]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->getById($id)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_code'       => ['required', 'string', 'max:50'],
            'hire_date'           => ['required', 'date'],
            'employment_type'     => ['nullable', 'integer', 'in:1,2,3'],
            'job_title'           => ['nullable', 'string', 'max:150'],
            'business_partner_id' => ['nullable', 'uuid'],
            'user_id'             => ['nullable', 'uuid'],
            'department_id'       => ['nullable', 'uuid'],
            'branch_id'           => ['nullable', 'uuid'],
            'status'              => ['nullable', 'integer', 'in:1,2,3'],
        ]);

        $emp = $this->service->create($data);

        return response()->json(['success' => true, 'data' => $emp], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'employee_code'       => ['sometimes', 'string', 'max:50'],
            'hire_date'           => ['sometimes', 'date'],
            'employment_type'     => ['sometimes', 'integer', 'in:1,2,3'],
            'job_title'           => ['sometimes', 'string', 'max:150'],
            'business_partner_id' => ['nullable', 'uuid'],
            'user_id'             => ['nullable', 'uuid'],
            'department_id'       => ['nullable', 'uuid'],
            'branch_id'           => ['nullable', 'uuid'],
            'status'              => ['sometimes', 'integer', 'in:1,2,3'],
            'termination_date'    => ['nullable', 'date'],
        ]);

        return response()->json(['success' => true, 'data' => $this->service->update($id, $data)]);
    }

    public function terminate(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'termination_date' => ['required', 'date'],
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->service->terminate($id, $data['termination_date']),
        ]);
    }
}
