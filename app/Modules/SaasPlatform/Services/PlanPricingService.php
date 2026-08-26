<?php

namespace App\Modules\SaasPlatform\Services;

use App\Modules\SaasPlatform\Models\PlanPrice;
use App\Modules\SaasPlatform\Models\PlanModule;
use App\Modules\SaasPlatform\Models\PlanFeature;
use App\Modules\SaasPlatform\Models\PlanVersionFeature;
use App\Modules\SaasPlatform\Models\PlanVersion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PlanPricingService
{
    public function addPrice(string $planVersionId, float $amount, int $billingPeriodDays, ?string $createdBy = null): PlanPrice
    {
        $version = PlanVersion::where('plan_version_id', $planVersionId)->whereNull('deleted_at')->firstOrFail();

        return PlanPrice::create([
            'plan_version_id'     => $planVersionId,
            'amount'              => $amount,
            'billing_period_days' => $billingPeriodDays,
            'status'              => 1,
            'created_by'          => $createdBy,
            'updated_by'          => $createdBy,
        ]);
    }

    public function addModule(string $planVersionId, string $code, string $name, ?string $createdBy = null): PlanModule
    {
        PlanVersion::where('plan_version_id', $planVersionId)->whereNull('deleted_at')->firstOrFail();

        return PlanModule::create([
            'plan_version_id' => $planVersionId,
            'code'            => $code,
            'name'            => $name,
            'status'          => 1,
            'created_by'      => $createdBy,
            'updated_by'      => $createdBy,
        ]);
    }

    public function addFeature(string $planModuleId, string $code, string $name, ?string $createdBy = null): PlanFeature
    {
        PlanModule::where('plan_module_id', $planModuleId)->whereNull('deleted_at')->firstOrFail();

        return PlanFeature::create([
            'plan_module_id' => $planModuleId,
            'code'           => $code,
            'name'           => $name,
            'status'         => 1,
            'created_by'     => $createdBy,
            'updated_by'     => $createdBy,
        ]);
    }

    public function enableFeatureForVersion(string $planVersionId, string $planFeatureId, bool $enabled = true, ?string $createdBy = null): PlanVersionFeature
    {
        return DB::transaction(function () use ($planVersionId, $planFeatureId, $enabled, $createdBy) {
            PlanVersion::where('plan_version_id', $planVersionId)->whereNull('deleted_at')->firstOrFail();
            PlanFeature::where('plan_feature_id', $planFeatureId)->whereNull('deleted_at')->firstOrFail();

            $existing = PlanVersionFeature::where('plan_version_id', $planVersionId)
                ->where('plan_feature_id', $planFeatureId)
                ->whereNull('deleted_at')
                ->first();

            if ($existing) {
                $existing->enabled = $enabled;
                $existing->updated_by = $createdBy;
                $existing->save();
                return $existing;
            }

            return PlanVersionFeature::create([
                'plan_version_id' => $planVersionId,
                'plan_feature_id' => $planFeatureId,
                'enabled'         => $enabled,
                'created_by'      => $createdBy,
                'updated_by'      => $createdBy,
            ]);
        });
    }
}