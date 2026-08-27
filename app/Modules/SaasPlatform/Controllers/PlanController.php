<?php

namespace App\Modules\SaasPlatform\Controllers;

use App\Base\Controller;
use App\Modules\SaasPlatform\Requests\CreatePlanRequest;
use App\Modules\SaasPlatform\DTOs\CreatePlanDTO;
use App\Modules\SaasPlatform\Services\PlanService;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function __construct(
        private readonly PlanService $planService
    ) {
    }

    public function store(CreatePlanRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized.',
            ], 401);
        }

        $dto = CreatePlanDTO::fromRequest($request->validated());
        $plan = $this->planService->createPlan($dto->code, $dto->name, $user->user_id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Plan created successfully.',
            'data'    => $plan,
        ], 201);
    }

    public function index(): JsonResponse
    {
        $plans = $this->planService->listActivePlans();

        return response()->json([
            'status' => 'success',
            'data'   => $plans,
        ]);
    }
}