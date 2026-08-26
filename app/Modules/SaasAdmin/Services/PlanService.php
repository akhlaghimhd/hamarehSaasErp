<?php

namespace App\Modules\SaasAdmin\Services;

use App\Modules\SaasAdmin\Models\Plan;
use App\Modules\SaasAdmin\Models\PlanVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class PlanService
{
    public function createPlan(string $code, string $name, ?string $createdBy = null): Plan
    {
        return DB::transaction(function () use ($code, $name, $createdBy) {
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

            $version = PlanVersion::create([
                'plan_id'        => $plan->plan_id,
                'version_number' => 1,
                'status'         => 1,
                'created_by'     => $createdBy,
                'updated_by'     => $createdBy,
            ]);

            $this->logEventOutbox(
                null,
                'plans',
                $plan->plan_id,
                'SaasAdmin.PlanCreated.v1',
                [
                    'plan_id'        => $plan->plan_id,
                    'code'           => $plan->code,
                    'name'           => $plan->name,
                    'plan_version_id'=> $version->plan_version_id,
                    'version_number' => 1,
                    'status'         => 1,
                ]
            );

            return $plan->load('versions');
        });
    }

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

            $version = PlanVersion::create([
                'plan_id'        => $planId,
                'version_number' => $versionNumber,
                'status'         => 1,
                'created_by'     => $createdBy,
                'updated_by'     => $createdBy,
            ]);

            $this->logEventOutbox(
                null,
                'plan_versions',
                $version->plan_version_id,
                'SaasAdmin.PlanVersionCreated.v1',
                [
                    'plan_id'         => $planId,
                    'plan_version_id' => $version->plan_version_id,
                    'version_number'  => $versionNumber,
                    'status'          => 1,
                ]
            );

            return $version;
        });
    }

    public function updatePlan(string $planId, string $name, ?int $status = null, ?string $updatedBy = null): Plan
    {
        $plan = Plan::where('plan_id', $planId)->whereNull('deleted_at')->firstOrFail();

        $plan->name = $name;
        if ($status !== null) {
            $plan->status = $status;
        }
        $plan->updated_by = $updatedBy;
        $plan->save();

        return $plan->fresh('versions');
    }

    public function softDeletePlan(string $planId, ?string $deletedBy = null): bool
    {
        $plan = Plan::where('plan_id', $planId)->whereNull('deleted_at')->firstOrFail();

        $plan->deleted_by = $deletedBy;
        $plan->save();

        return (bool) $plan->delete();
    }

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
     * Platform-level entities (plans) may have null tenant_id in outbox.
     * Uses empty string fallback if DB requires NOT NULL — adjust if schema differs.
     */
    private function logEventOutbox(
        ?string $tenantId,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload
    ): void {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $tenantId ?? '00000000-0000-0000-0000-000000000000',
            'aggregate_type' => $aggregateType,
            'aggregate_id'   => $aggregateId,
            'event_type'     => $eventType,
            'payload'        => json_encode($payload),
            'status'         => 1,
            'created_at'     => now(),
        ]);
    }
}