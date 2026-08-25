<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Modules\SaasAdmin\Models\PlanOffer;
use App\Modules\SaasAdmin\Models\PlanOfferDiscount;
use App\Modules\SaasAdmin\Models\OfferAvailableAddon;
use App\Modules\SaasAdmin\Services\PlanService;
use App\Modules\SaasAdmin\Services\AddonService;
use App\Modules\SaasAdmin\Services\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlanService $planService;
    private AddonService $addonService;
    private OfferService $offerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planService = app(PlanService::class);
        $this->addonService = app(AddonService::class);
        $this->offerService = app(OfferService::class);
    }

    public function test_create_offer_and_discount(): void
    {
        $plan = $this->planService->createPlan('BASIC', 'Basic');
        $version = $plan->versions->first();

        $offer = $this->offerService->createOffer($version->plan_version_id, 'Launch Offer');
        $discount = $this->offerService->addDiscount($offer->plan_offer_id, 10.0000, 1);

        $this->assertDatabaseHas('plan_offers', [
            'plan_offer_id'   => $offer->plan_offer_id,
            'plan_version_id' => $version->plan_version_id,
            'name'            => 'Launch Offer',
        ]);

        $this->assertDatabaseHas('plan_offer_discounts', [
            'plan_offer_discount_id' => $discount->plan_offer_discount_id,
            'discount_value'         => 10.0000,
            'discount_type'          => 1,
        ]);
    }

    public function test_attach_addon_to_offer(): void
    {
        $plan = $this->planService->createPlan('PRO', 'Pro');
        $version = $plan->versions->first();
        $offer = $this->offerService->createOffer($version->plan_version_id, 'Pro Launch');
        $addon = $this->addonService->createAddon('EXTRA', 'Extra Feature');

        $link = $this->offerService->attachAddonToOffer($offer->plan_offer_id, $addon->addon_id);

        $this->assertDatabaseHas('offer_available_addons', [
            'offer_available_addon_id' => $link->offer_available_addon_id,
            'plan_offer_id'            => $offer->plan_offer_id,
            'addon_id'                 => $addon->addon_id,
        ]);
    }
}