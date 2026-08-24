<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Modules\SaasAdmin\Models\Tenant;
use App\Modules\SaasAdmin\Models\Plan;
use App\Modules\SaasAdmin\Models\PlanVersion;
use App\Modules\SaasAdmin\Models\Subscription;
use App\Modules\SaasAdmin\Models\SubscriptionEvent;
use App\Modules\SaasAdmin\Services\PlanService;
use App\Modules\SaasAdmin\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlanService $planService;
    private SubscriptionService $subscriptionService;
    private Tenant $tenant;
    private PlanVersion $planVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planService = app(PlanService::class);
        $this->subscriptionService = app(SubscriptionService::class);

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'TEST_TENANT',
            'status'      => 1,
        ]);

        $plan = $this->planService->createPlan('BASIC', 'Basic Plan');
        $this->planVersion = $plan->versions->first();
    }

    public function test_create_subscription_successfully(): void
    {
        $subscription = $this->subscriptionService->createSubscription(
            $this->tenant->tenant_id,
            $this->planVersion->plan_version_id
        );

        $this->assertDatabaseHas('subscriptions', [
            'subscription_id'  => $subscription->subscription_id,
            'tenant_id'        => $this->tenant->tenant_id,
            'plan_version_id'  => $this->planVersion->plan_version_id,
            'status'           => 1,
        ]);

        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->subscription_id,
            'event_type'      => SubscriptionService::EVENT_CREATED,
        ]);

        $this->assertCount(1, $subscription->events);
    }

    public function test_cancel_subscription(): void
    {
        $subscription = $this->subscriptionService->createSubscription(
            $this->tenant->tenant_id,
            $this->planVersion->plan_version_id
        );

        $cancelled = $this->subscriptionService->cancelSubscription($subscription->subscription_id);

        $this->assertEquals(4, $cancelled->status);
        $this->assertNotNull($cancelled->end_date);

        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->subscription_id,
            'event_type'      => SubscriptionService::EVENT_CANCELLED,
        ]);
    }

    public function test_get_active_subscription(): void
    {
        $this->subscriptionService->createSubscription(
            $this->tenant->tenant_id,
            $this->planVersion->plan_version_id
        );

        $active = $this->subscriptionService->getActiveSubscription($this->tenant->tenant_id);

        $this->assertNotNull($active);
        $this->assertEquals(1, $active->status);
        $this->assertEquals($this->tenant->tenant_id, $active->tenant_id);
    }
}