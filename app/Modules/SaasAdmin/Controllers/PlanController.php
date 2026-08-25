<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Requests\CreatePlanRequest;
use App\Modules\SaasAdmin\DTOs\CreatePlanDTO;
use App\Modules\SaasAdmin\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PlanController extends Controller
{
    public function __construct(
        private readonly PlanService $planService
    ) {
    }

    public function store(CreatePlanRequest $request): JsonResponse
    {
        $userId = Auth::guard('api')->id();
        $dto = CreatePlanDTO::fromRequest($request->validated());

        $plan = $this->planService->createPlan($dto->code, $dto->name, $userId);

        return response()->json([
            'message' => 'Plan created successfully.',
            'data'    => $plan,
        ], 201);
    }

    public function index(): JsonResponse
    {
        $plans = $this->planService->listActivePlans();

        return response()->json([
            'data' => $plans,
        ]);
    }
}