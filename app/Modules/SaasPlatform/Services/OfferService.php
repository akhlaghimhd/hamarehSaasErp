<?php

namespace App\Modules\SaasPlatform\Services;

use App\Modules\SaasPlatform\Models\PlanOffer;
use App\Modules\SaasPlatform\Models\PlanOfferDiscount;
use App\Modules\SaasPlatform\Models\OfferAvailableAddon;
use App\Modules\SaasPlatform\Models\PlanVersion;
use App\Modules\SaasPlatform\Models\Addon;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OfferService
{
    public function createOffer(
        string $planVersionId,
        string $name,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?string $createdBy = null
    ): PlanOffer {
        PlanVersion::where('plan_version_id', $planVersionId)->whereNull('deleted_at')->firstOrFail();

        return PlanOffer::create([
            'plan_version_id' => $planVersionId,
            'name'            => $name,
            'status'          => 1,
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'created_by'      => $createdBy,
            'updated_by'      => $createdBy,
        ]);
    }

    public function addDiscount(
        string $planOfferId,
        float $discountValue,
        int $discountType,
        ?string $createdBy = null
    ): PlanOfferDiscount {
        PlanOffer::where('plan_offer_id', $planOfferId)->whereNull('deleted_at')->firstOrFail();

        return PlanOfferDiscount::create([
            'plan_offer_id'  => $planOfferId,
            'discount_value' => $discountValue,
            'discount_type'  => $discountType,
            'created_by'     => $createdBy,
            'updated_by'     => $createdBy,
        ]);
    }

    public function attachAddonToOffer(
        string $planOfferId,
        string $addonId,
        ?string $createdBy = null
    ): OfferAvailableAddon {
        return DB::transaction(function () use ($planOfferId, $addonId, $createdBy) {
            PlanOffer::where('plan_offer_id', $planOfferId)->whereNull('deleted_at')->firstOrFail();
            Addon::where('addon_id', $addonId)->whereNull('deleted_at')->firstOrFail();

            return OfferAvailableAddon::create([
                'plan_offer_id' => $planOfferId,
                'addon_id'      => $addonId,
                'created_by'    => $createdBy,
                'updated_by'    => $createdBy,
            ]);
        });
    }
}