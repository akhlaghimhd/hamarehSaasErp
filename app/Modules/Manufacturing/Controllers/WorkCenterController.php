<?php

namespace App\Modules\Manufacturing\Controllers;

use App\Base\Controller;
use App\Modules\Manufacturing\Requests\StoreWorkCenterRequest;
use App\Modules\Manufacturing\Services\WorkCenterService;
use Illuminate\Http\JsonResponse;

class WorkCenterController extends Controller
{
    public function __construct(
        private readonly WorkCenterService $workCenterService
    ) {}

    public function store(StoreWorkCenterRequest $request): JsonResponse
    {
        // Controller ONLY validates request, maps to DTO, and calls Service
        $dto = $request->toDTO();
        
        $workCenter = $this->workCenterService->createWorkCenter($dto);

        return response()->json([
            'message' => 'Work center created successfully.',
            'data' => $workCenter
        ], 201);
    }
}