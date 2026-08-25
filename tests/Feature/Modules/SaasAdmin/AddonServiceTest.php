<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Modules\SaasAdmin\Models\Tenant;
use App\Modules\SaasAdmin\Models\Addon;
use App\Modules\SaasAdmin\Models\SubscriptionAddon;
use App\Modules\SaasAdmin\Services\PlanService;
use App\Modules\SaasAdmin\Services\SubscriptionService;
use App\Modules\SaasAdmin\Services\AddonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddonServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlanService $planService;
    private SubscriptionService $subscriptionService;
    private AddonService $addonService;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planService = app(PlanService::class);
        $this->subscriptionService = app(SubscriptionService::class);
        $this->addonService = app(AddonService::class);

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'ADDON_TEST',
            'status'      => 1,
        ]);
    }

    public function test_create_addon_successfully(): void
    {
        $addon = $this->addonService->createAddon('EXTRA_STORAGE', 'Extra Storage 10GB');

        $this->assertDatabaseHas('addons', [
            'addon_id' => $addon->addon_id,
            'code'     => 'EXTRA_STORAGE',
            'name'     => 'Extra Storage 10GB',
            'status'   => 1,
        ]);
    }

    public function test_create_addon_throws_on_duplicate_code(): void
    {
        $this->addonService->createAddon('EXTRA_STORAGE', 'Extra Storage 10GB');

        $this->expectException(\InvalidArgumentException::class);

        $this->addonService->createAddon('EXTRA_STORAGE', 'Another Storage');
    }

    public function test_attach_addon_to_subscription(): void
    {
        $plan = $this->planService->createPlan('BASIC', 'Basic');
        $version = $plan->versions->first();

        $subscription = $this->subscriptionService->createSubscription(
            $this->tenant->tenant_id,
            $version->plan_version_id
        );

        $addon = $this->addonService->createAddon('EXTRA_USERS', 'Extra 5 Users');

        $subscriptionAddon = $this->addonService->attachAddonToSubscription(
            $subscription->subscription_id,
            $addon->addon_id,
            15.5000
        );

        $this->assertDatabaseHas('subscription_addons', [
            'subscription_addon_id' => $subscriptionAddon->subscription_addon_id,
            'subscription_id'       => $subscription->subscription_id,
            'addon_id'              => $addon->addon_id,
            'amount'                => 15.5000,
            'status'                => 1,
        ]);
    }

    public function test_list_active_addons(): void
    {
        $this->addonService->createAddon('A1', 'Addon 1');
        $this->addonService->createAddon('A2', 'Addon 2');

        $addons = $this->addonService->listActiveAddons();

        $this->assertCount(2, $addons);
    }
}