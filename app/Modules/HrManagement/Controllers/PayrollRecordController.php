<?php

namespace App\Modules\HrManagement\Controllers;

use App\Base\Controller;
use App\Modules\HrManagement\DTOs\CreatePayrollRecordDTO;
use App\Modules\HrManagement\Requests\CreatePayrollRecordRequest;
use App\Modules\HrManagement\Services\PayrollRecordService;
use Illuminate\Http\JsonResponse;

class PayrollRecordController extends Controller
{
    public function __construct(
        private readonly PayrollRecordService $payrollService
    ) {}

    public function store(CreatePayrollRecordRequest $request): JsonResponse
    {
        $dto = CreatePayrollRecordDTO::fromRequest($request->validated());
        
        $payroll = $this->payrollService->generatePayroll($dto);

        // واکشی مجدد رکورد برای دریافت مقدار محاسبه شده net_payable از دیتابیس
        $payroll->refresh();

        return response()->json([
            'message' => 'Payroll record generated successfully.',
            'data' => $payroll
        ], 201);
    }
}