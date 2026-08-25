<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Requests\CreateSubscriptionRequest;
use App\Modules\SaasAdmin\DTOs\CreateSubscriptionDTO;
use App\Modules\SaasAdmin\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService
    ) {
    }

    public function store(CreateSubscriptionRequest $request): JsonResponse
    {
        $userId = Auth::guard('api')->id();
        $dto = CreateSubscriptionDTO::fromRequest($request->validated());

        $startDate = $dto->startDate ? Carbon::parse($dto->startDate) : null;

        $subscription = $this->subscriptionService->createSubscription(
            $dto->tenantId,
            $dto->planVersionId,
            $startDate,
            $userId
        );

        return response()->json([
            'message' => 'Subscription created successfully.',
            'data'    => $subscription,
        ], 201);
    }
}