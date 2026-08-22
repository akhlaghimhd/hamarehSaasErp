<?php

namespace App\Modules\HrManagement\DTOs;

readonly class CreateAttendanceRecordDTO
{
    public function __construct(
        public string $employeeId,
        public string $date,
        public ?string $checkIn = null,
        public ?string $checkOut = null,
        public int $status,
        public ?float $workHours = null,
        public ?float $overtimeHours = null,
        public ?string $notes = null,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            employeeId: $validated['employee_id'],
            date: $validated['date'],
            checkIn: $validated['check_in'] ?? null,
            checkOut: $validated['check_out'] ?? null,
            status: $validated['status'],
            workHours: isset($validated['work_hours']) ? (float) $validated['work_hours'] : null,
            overtimeHours: isset($validated['overtime_hours']) ? (float) $validated['overtime_hours'] : null,
            notes: $validated['notes'] ?? null,
        );
    }
}