<?php

namespace App\Modules\SaasAdmin\Services;

use App\Modules\SaasAdmin\Models\Plan;
use App\Modules\SaasAdmin\Models\PlanVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class PlanService
{
    /**
     * Create a new Plan with its first version (version_number = 1).
     */
    public function createPlan(string $code, string $name, ?string $createdBy = null): Plan
    {
        return DB::transaction(function () use ($code, $name, $createdBy) {
            // Prevent duplicate active code
            $exists = Plan::where('code', $code)->whereNull('deleted_at')->exists();
            if ($exists) {
                throw new InvalidArgumentException("Plan code [{$code}] already exists.");
            }

            $plan = Plan::create([
                'code'       => $code,
                'name'       => $name,
                'status'     => 1,
                'created_by' => $createdBy,
                'updated_by' => $createdBy,
            ]);

            // Automatically create the first version
            PlanVersion::create([
                'plan_id'        => $plan->plan_id,
                'version_number' => 1,
                'status'         => 1,
                'created_by'     => $createdBy,
                'updated_by'     => $createdBy,
            ]);

            return $plan->load('versions');
        });
    }

    /**
     * Create a new version for an existing Plan.
     */
    public function createPlanVersion(string $planId, int $versionNumber, ?string $createdBy = null): PlanVersion
    {
        return DB::transaction(function () use ($planId, $versionNumber, $createdBy) {
            $plan = Plan::where('plan_id', $planId)->whereNull('deleted_at')->firstOrFail();

            $exists = PlanVersion::where('plan_id', $planId)
                ->where('version_number', $versionNumber)
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                throw new InvalidArgumentException(
                    "Version [{$versionNumber}] already exists for plan [{$plan->code}]."
                );
            }

            return PlanVersion::create([
                'plan_id'        => $planId,
                'version_number' => $versionNumber,
                'status'         => 1,
                'created_by'     => $createdBy,
                'updated_by'     => $createdBy,
            ]);
        });
    }

    /**
     * List all active plans with their versions.
     */
    public function listActivePlans(): Collection
    {
        return Plan::with(['versions' => function ($query) {
            $query->whereNull('deleted_at')->orderBy('version_number');
        }])
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->orderBy('code')
            ->get();
    }

    /**
     * Soft-delete a plan.
     */
    public function softDeletePlan(string $planId, ?string $deletedBy = null): bool
    {
        $plan = Plan::where('plan_id', $planId)->whereNull('deleted_at')->firstOrFail();

        $plan->deleted_by = $deletedBy;
        $plan->save();

        return (bool) $plan->delete();
    }
}