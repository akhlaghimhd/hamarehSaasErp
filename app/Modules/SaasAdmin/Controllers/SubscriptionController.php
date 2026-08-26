<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Requests\CreateSubscriptionRequest;
use App\Modules\SaasAdmin\DTOs\CreateSubscriptionDTO;
use App\Modules\SaasAdmin\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService
    ) {
    }

    public function store(CreateSubscriptionRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized.',
            ], 401);
        }

        $dto = CreateSubscriptionDTO::fromRequest($request->validated());
        $startDate = $dto->startDate ? Carbon::parse($dto->startDate) : null;

        $subscription = $this->subscriptionService->createSubscription(
            $dto->tenantId,
            $dto->planVersionId,
            $startDate,
            $user->user_id
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Subscription created successfully.',
            'data'    => $subscription,
        ], 201);
    }

    public function cancel(string $subscriptionId): JsonResponse
    {
        $user = $this->request()->user();
        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized.',
            ], 401);
        }

        $subscription = $this->subscriptionService->cancelSubscription(
            $subscriptionId,
            $user->user_id
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Subscription cancelled successfully.',
            'data'    => $subscription,
        ]);
    }

    public function show(string $subscriptionId): JsonResponse
    {
        $user = $this->request()->user();
        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized.',
            ], 401);
        }

        $subscription = $this->subscriptionService->getActiveSubscription($user->tenant_id ?? $this->request()->headers->get('X-Tenant-ID'));

        if (!$subscription || $subscription->subscription_id !== $subscriptionId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Subscription not found or access denied.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $subscription,
        ]);
    }

    public function update(string $subscriptionId, UpdateSubscriptionRequest $request): JsonResponse
    {
        $user = $this->request()->user();
        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized.',
            ], 401);
        }

        $dto = UpdateSubscriptionDTO::fromRequest($request->validated());
        $subscription = $this->subscriptionService->updateSubscriptionStatus(
            $subscriptionId,
            $dto->status,
            $user->user_id
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Subscription updated successfully.',
            'data'    => $subscription,
        ]);
    }
}