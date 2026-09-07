<?php

namespace App\Modules\HrManagement\Controllers;

use App\Base\Controller;
use App\Modules\HrManagement\Services\PayrollRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollRecordController extends Controller
{
    public function __construct(
        private readonly PayrollRecordService $service
    ) {
    }

    public function index(string $employeeId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->listForEmployee($employeeId),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id'        => ['required', 'uuid'],
            'fiscal_period_id'   => ['required', 'uuid'],
            'base_salary'        => ['required', 'numeric', 'min:0'],
            'allowances_total'   => ['nullable', 'numeric', 'min:0'],
            'deductions_total'   => ['nullable', 'numeric', 'min:0'],
            'tax_withheld'       => ['nullable', 'numeric', 'min:0'],
            'insurance_premium'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $row = $this->service->create($data);

        return response()->json(['success' => true, 'data' => $row], 201);
    }

    public function disburse(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'journal_entry_id' => ['nullable', 'uuid'],
        ]);

        $row = $this->service->markDisbursed($id, $data['journal_entry_id'] ?? null);

        return response()->json(['success' => true, 'data' => $row]);
    }
}
