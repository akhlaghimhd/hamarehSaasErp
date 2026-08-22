<?php

namespace App\Modules\HrManagement\Controllers;

use App\Base\Controller; // کنترلر پایه لاغر
use App\Modules\HrManagement\DTOs\CreateEmployeeProfileDTO;
use App\Modules\HrManagement\Requests\CreateEmployeeProfileRequest;
use App\Modules\HrManagement\Services\EmployeeProfileService;
use Illuminate\Http\JsonResponse;

class EmployeeProfileController extends Controller
{
    public function __construct(
        private readonly EmployeeProfileService $profileService
    ) {}

    /**
     * API First: فقط تبدیل Request به DTO و تحویل به Service
     */
    public function store(CreateEmployeeProfileRequest $request): JsonResponse
    {
        $dto = CreateEmployeeProfileDTO::fromRequest($request->validated());
        
        $profile = $this->profileService->createProfile($dto);

        return response()->json([
            'message' => 'Employee profile created successfully.',
            'data' => $profile
        ], 201);
    }
}