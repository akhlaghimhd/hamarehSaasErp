<?php

namespace App\Modules\HrManagement\Controllers;

use App\Base\Controller;
use App\Modules\HrManagement\Services\EmployeeProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeProfileController extends Controller
{
    public function __construct(
        private readonly EmployeeProfileService $service
    ) {
    }

    public function show(string $employeeId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getByEmployee($employeeId),
        ]);
    }

    public function upsert(Request $request, string $employeeId): JsonResponse
    {
        $data = $request->validate([
            'national_code'           => ['nullable', 'string', 'max:20'],
            'father_name'             => ['nullable', 'string', 'max:100'],
            'gender'                  => ['nullable', 'integer', 'in:1,2'],
            'marital_status'          => ['nullable', 'integer', 'in:1,2'],
            'birth_date'              => ['nullable', 'date'],
            'address'                 => ['nullable', 'string'],
            'emergency_contact_name'  => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $profile = $this->service->upsert($employeeId, $data);

        return response()->json(['success' => true, 'data' => $profile]);
    }
}
