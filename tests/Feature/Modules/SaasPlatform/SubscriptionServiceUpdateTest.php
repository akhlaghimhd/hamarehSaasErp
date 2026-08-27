<?php

namespace Tests\Feature\Modules\SaasPlatform;

use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\SaasPlatform\Services\PlanService;
use App\Modules\SaasPlatform\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceUpdateTest extends TestCase
{
    use RefreshDatabase;

    private PlanService $planService;
    private SubscriptionService $subscriptionService;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planService = app(PlanService::class);
        $this->subscriptionService = app(SubscriptionService::class);
        $this->tenant = Tenant::factory()->create(['tenant_code' => 'SUB_UPD', 'status' => 1]);
    }

    public function test_update_subscription_status(): void
    {
        $plan = $this->planService->createPlan('BASIC', 'Basic');
        $version = $plan->versions->first();

        $subscription = $this->subscriptionService->createSubscription(
            $this->tenant->tenant_id,
            $version->plan_version_id
        );

        $updated = $this->subscriptionService->updateSubscriptionStatus($subscription->subscription_id, 6);

        $this->assertEquals(6, $updated->status);
    }

    public function test_soft_delete_subscription(): void
    {
        $plan = $this->planService->createPlan('BASIC2', 'Basic 2');
        $version = $plan->versions->first();

        $subscription = $this->subscriptionService->createSubscription(
            $this->tenant->tenant_id,
            $version->plan_version_id
        );

        $result = $this->subscriptionService->softDeleteSubscription($subscription->subscription_id);

        $this->assertTrue($result);
        $this->assertSoftDeleted('subscriptions', ['subscription_id' => $subscription->subscription_id]);
    }
}