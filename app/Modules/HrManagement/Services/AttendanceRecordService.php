<?php

namespace App\Modules\HrManagement\Services;

use App\Modules\HrManagement\DTOs\CreateAttendanceRecordDTO;
use App\Modules\HrManagement\Models\AttendanceRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AttendanceRecordService
{
    public function recordAttendance(CreateAttendanceRecordDTO $dto): AttendanceRecord
    {
        try {
            return DB::transaction(function () use ($dto) {
                $tenantId = app('current_tenant_id');

                $attendance = AttendanceRecord::create([
                    'tenant_id' => $tenantId, // ذکر صریح شناسه مستأجر
                    'employee_id' => $dto->employeeId,
                    'date' => $dto->date,
                    'check_in' => $dto->checkIn,
                    'check_out' => $dto->checkOut,
                    'status' => $dto->status,
                    'work_hours' => $dto->workHours,
                    'overtime_hours' => $dto->overtimeHours,
                    'notes' => $dto->notes,
                ]);

                // در صورت نیاز انتشار رویداد برای ماژول حقوق و دستمزد
                // EventOutbox::create(['event_type' => 'hr.attendance.recorded', ...]);

                return $attendance;
            });
        } catch (Exception $e) {
            Log::error('Failed to record attendance: ' . $e->getMessage(), [
                'employee_id' => $dto->employeeId,
                'date' => $dto->date,
                'tenant_id' => app('current_tenant_id') ?? 'UNKNOWN'
            ]);
            throw $e;
        }
    }
}