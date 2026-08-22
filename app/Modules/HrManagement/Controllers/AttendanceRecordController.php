<?php

namespace App\Modules\HrManagement\Controllers;

use App\Base\Controller;
use App\Modules\HrManagement\DTOs\CreateAttendanceRecordDTO;
use App\Modules\HrManagement\Requests\CreateAttendanceRecordRequest;
use App\Modules\HrManagement\Services\AttendanceRecordService;
use Illuminate\Http\JsonResponse;

class AttendanceRecordController extends Controller
{
    public function __construct(
        private readonly AttendanceRecordService $attendanceService
    ) {}

    /**
     * API-First: تبدیل Request به DTO و فراخوانی Service
     */
    public function store(CreateAttendanceRecordRequest $request): JsonResponse
    {
        $dto = CreateAttendanceRecordDTO::fromRequest($request->validated());
        
        $attendance = $this->attendanceService->recordAttendance($dto);

        return response()->json([
            'message' => 'Attendance record created successfully.',
            'data' => $attendance
        ], 201);
    }
}