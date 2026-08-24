<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Modules\SaasAdmin\Models\Plan;
use App\Modules\SaasAdmin\Models\PlanVersion;
use App\Modules\SaasAdmin\Models\PlanPrice;
use App\Modules\SaasAdmin\Models\PlanModule;
use App\Modules\SaasAdmin\Models\PlanFeature;
use App\Modules\SaasAdmin\Models\PlanVersionFeature;
use App\Modules\SaasAdmin\Services\PlanService;
use App\Modules\SaasAdmin\Services\PlanPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlanService $planService;
    private PlanPricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planService = app(PlanService::class);
        $this->pricingService = app(PlanPricingService::class);
    }

    public function test_add_price_to_plan_version(): void
    {
        $plan = $this->planService->createPlan('BASIC', 'Basic Plan');
        $version = $plan->versions->first();

        $price = $this->pricingService->addPrice($version->plan_version_id, 99.9900, 30);

        $this->assertDatabaseHas('plan_prices', [
            'plan_price_id'       => $price->plan_price_id,
            'plan_version_id'     => $version->plan_version_id,
            'amount'              => 99.9900,
            'billing_period_days' => 30,
            'status'              => 1,
        ]);
    }

    public function test_add_module_and_feature(): void
    {
        $plan = $this->planService->createPlan('PRO', 'Pro Plan');
        $version = $plan->versions->first();

        $module = $this->pricingService->addModule($version->plan_version_id, 'INV', 'Inventory');
        $feature = $this->pricingService->addFeature($module->plan_module_id, 'STOCK', 'Stock Management');

        $this->assertDatabaseHas('plan_modules', [
            'plan_module_id'  => $module->plan_module_id,
            'plan_version_id' => $version->plan_version_id,
            'code'            => 'INV',
        ]);

        $this->assertDatabaseHas('plan_features', [
            'plan_feature_id' => $feature->plan_feature_id,
            'plan_module_id'  => $module->plan_module_id,
            'code'            => 'STOCK',
        ]);
    }

    public function test_enable_feature_for_version(): void
    {
        $plan = $this->planService->createPlan('ENT', 'Enterprise');
        $version = $plan->versions->first();

        $module = $this->pricingService->addModule($version->plan_version_id, 'ACC', 'Accounting');
        $feature = $this->pricingService->addFeature($module->plan_module_id, 'GL', 'General Ledger');

        $link = $this->pricingService->enableFeatureForVersion(
            $version->plan_version_id,
            $feature->plan_feature_id,
            true
        );

        $this->assertDatabaseHas('plan_version_features', [
            'plan_version_feature_id' => $link->plan_version_feature_id,
            'plan_version_id'         => $version->plan_version_id,
            'plan_feature_id'         => $feature->plan_feature_id,
            'enabled'                 => true,
        ]);
    }
}