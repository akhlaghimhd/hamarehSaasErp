<?php

namespace App\Modules\HrManagement\Services;

use App\Modules\HrManagement\Models\Employee;
use App\Modules\HrManagement\Models\EmployeeProfile;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmployeeProfileService
{
    public function getByEmployee(string $employeeId): EmployeeProfile
    {
        $profile = EmployeeProfile::query()->where('employee_id', $employeeId)->first();
        if (!$profile) {
            throw new NotFoundHttpException('Employee profile not found.');
        }

        return $profile;
    }

    public function upsert(string $employeeId, array $data): EmployeeProfile
    {
        try {
            return DB::transaction(function () use ($employeeId, $data) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $employee = Employee::query()->find($employeeId);
                if (!$employee) {
                    throw new NotFoundHttpException('Employee not found.');
                }

                $existing = EmployeeProfile::query()
                    ->where('employee_id', $employeeId)
                    ->lockForUpdate()
                    ->first();

                $payload = [
                    'national_code'           => $data['national_code'] ?? null,
                    'father_name'             => $data['father_name'] ?? null,
                    'gender'                  => $data['gender'] ?? null,
                    'marital_status'          => $data['marital_status'] ?? null,
                    'birth_date'              => $data['birth_date'] ?? null,
                    'address'                 => $data['address'] ?? null,
                    'emergency_contact_name'  => $data['emergency_contact_name'] ?? null,
                    'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                ];

                if ($existing) {
                    $payload['updated_by'] = $userId;
                    $payload['row_version'] = ((int) ($existing->row_version ?? 1)) + 1;
                    $existing->update($payload);

                    return $existing->fresh();
                }

                return EmployeeProfile::create(array_merge($payload, [
                    'tenant_id'   => $tenantId,
                    'employee_id' => $employeeId,
                    'created_by'  => $userId,
                    'row_version' => 1,
                ]));
            });
        } catch (Exception $e) {
            Log::error('Failed to upsert employee profile: ' . $e->getMessage());
            throw $e;
        }
    }
}
