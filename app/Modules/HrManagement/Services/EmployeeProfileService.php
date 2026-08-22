<?php

namespace App\Modules\HrManagement\Services;

use App\Modules\HrManagement\DTOs\CreateEmployeeProfileDTO;
use App\Modules\HrManagement\Models\EmployeeProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class EmployeeProfileService
{
    /**
     * ایجاد پروفایل کارمند با رعایت Transaction و قوانین ایزوله‌سازی
     */
    public function createProfile(CreateEmployeeProfileDTO $dto): EmployeeProfile
    {
        try {
            return DB::transaction(function () use ($dto) {
                
                $tenantId = app('current_tenant_id');

                $profile = EmployeeProfile::create([
                    'tenant_id' => $tenantId, // Set explicitly even if TenantScoped might auto-inject, for safety.
                    'employee_id' => $dto->employeeId,
                    'first_name' => $dto->firstName,
                    'last_name' => $dto->lastName,
                    'national_code' => $dto->nationalCode,
                    'birth_date' => $dto->birthDate,
                    'gender' => $dto->gender,
                    'marital_status' => $dto->maritalStatus,
                    'address' => $dto->address,
                    'emergency_contact' => $dto->emergencyContact,
                ]);

                // در صورت نیاز به انتشار رویداد برای سایر ماژول‌ها (Event-Driven)
                // EventOutbox::create(['event_type' => 'hr.employee_profile.created', ...]);

                return $profile;
            });
        } catch (Exception $e) {
            Log::error('Failed to create Employee Profile: ' . $e->getMessage(), [
                'employee_id' => $dto->employeeId,
                'tenant_id' => app('current_tenant_id') ?? 'UNKNOWN'
            ]);
            throw $e;
        }
    }
}