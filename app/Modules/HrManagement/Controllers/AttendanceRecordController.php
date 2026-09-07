<?php

namespace App\Modules\HrManagement\Controllers;

use App\Base\Controller;
use App\Modules\HrManagement\Services\AttendanceRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceRecordController extends Controller
{
    public function __construct(
        private readonly AttendanceRecordService $service
    ) {
    }

    public function index(string $employeeId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->listForEmployee($employeeId),
        ]);
    }

    public function clockIn(Request $request, string $employeeId): JsonResponse
    {
        $data = $request->validate([
            'attendance_date' => ['nullable', 'date'],
        ]);

        $row = $this->service->clockIn($employeeId, $data['attendance_date'] ?? null);

        return response()->json(['success' => true, 'data' => $row], 201);
    }

    public function clockOut(Request $request, string $employeeId): JsonResponse
    {
        $data = $request->validate([
            'attendance_date' => ['nullable', 'date'],
            'overtime_hours'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $row = $this->service->clockOut(
            $employeeId,
            $data['attendance_date'] ?? null,
            (float) ($data['overtime_hours'] ?? 0)
        );

        return response()->json(['success' => true, 'data' => $row]);
    }
}
