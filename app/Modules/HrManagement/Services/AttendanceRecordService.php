<?php

namespace App\Modules\HrManagement\Services;

use App\Modules\HrManagement\Models\AttendanceRecord;
use App\Modules\HrManagement\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AttendanceRecordService
{
    public const STATUS_PRESENT = 1;
    public const STATUS_ABSENT = 2;
    public const STATUS_LEAVE = 3;
    public const STATUS_MISSION = 4;

    public function listForEmployee(string $employeeId): Collection
    {
        return AttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->orderByDesc('attendance_date')
            ->get();
    }

    public function clockIn(string $employeeId, ?string $attendanceDate = null): AttendanceRecord
    {
        try {
            return DB::transaction(function () use ($employeeId, $attendanceDate) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $employee = Employee::query()->find($employeeId);
                if (!$employee) {
                    throw new NotFoundHttpException('Employee not found.');
                }
                if ((int) $employee->status !== EmployeeService::STATUS_ACTIVE) {
                    throw new ConflictHttpException('Only active employees can clock in.');
                }

                $date = $attendanceDate ?? now()->toDateString();

                $existing = AttendanceRecord::query()
                    ->where('employee_id', $employeeId)
                    ->whereDate('attendance_date', $date)
                    ->first();

                if ($existing && $existing->clock_in) {
                    throw new ConflictHttpException('Already clocked in for this date.');
                }

                if ($existing) {
                    $existing->update([
                        'clock_in'    => now(),
                        'status'      => self::STATUS_PRESENT,
                        'updated_by'  => $userId,
                        'row_version' => ((int) ($existing->row_version ?? 1)) + 1,
                    ]);

                    return $existing->fresh();
                }

                return AttendanceRecord::create([
                    'tenant_id'        => $tenantId,
                    'employee_id'      => $employeeId,
                    'attendance_date'  => $date,
                    'clock_in'         => now(),
                    'clock_out'        => null,
                    'overtime_hours'   => 0,
                    'delay_hours'      => 0,
                    'status'           => self::STATUS_PRESENT,
                    'created_by'       => $userId,
                    'row_version'      => 1,
                ]);
            });
        } catch (Exception $e) {
            Log::error('Failed to clock in: ' . $e->getMessage());
            throw $e;
        }
    }

    public function clockOut(string $employeeId, ?string $attendanceDate = null, float $overtimeHours = 0): AttendanceRecord
    {
        try {
            return DB::transaction(function () use ($employeeId, $attendanceDate, $overtimeHours) {
                $date = $attendanceDate ?? now()->toDateString();

                $record = AttendanceRecord::query()
                    ->where('employee_id', $employeeId)
                    ->whereDate('attendance_date', $date)
                    ->lockForUpdate()
                    ->first();

                if (!$record || !$record->clock_in) {
                    throw new ConflictHttpException('No open clock-in found for this date.');
                }
                if ($record->clock_out) {
                    throw new ConflictHttpException('Already clocked out for this date.');
                }

                $record->update([
                    'clock_out'      => now(),
                    'overtime_hours' => $overtimeHours,
                    'updated_by'     => Context::get('user_id'),
                    'row_version'    => ((int) ($record->row_version ?? 1)) + 1,
                ]);

                return $record->fresh();
            });
        } catch (Exception $e) {
            Log::error('Failed to clock out: ' . $e->getMessage());
            throw $e;
        }
    }
}
