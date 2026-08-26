<?php

namespace App\Modules\SaasPlatform\Services;

use App\Modules\SaasPlatform\Models\Subscription;
use App\Modules\SaasPlatform\Models\SubscriptionEvent;
use App\Modules\SaasPlatform\Models\PlanVersion;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SubscriptionService
{
    public const EVENT_CREATED = 1;
    public const EVENT_ACTIVATED = 2;
    public const EVENT_RENEWED = 3;
    public const EVENT_CANCELLED = 4;
    public const EVENT_EXPIRED = 5;
    public const EVENT_SUSPENDED = 6;

    public function createSubscription(
        string $tenantId,
        string $planVersionId,
        ?Carbon $startDate = null,
        ?string $createdBy = null
    ): Subscription {
        return DB::transaction(function () use ($tenantId, $planVersionId, $startDate, $createdBy) {
            Tenant::where('tenant_id', $tenantId)->whereNull('deleted_at')->firstOrFail();
            PlanVersion::where('plan_version_id', $planVersionId)->whereNull('deleted_at')->firstOrFail();

            $start = $startDate ?? Carbon::now();

            $subscription = Subscription::create([
                'tenant_id'         => $tenantId,
                'plan_version_id'   => $planVersionId,
                'status'            => 1,
                'start_date'        => $start,
                'next_billing_date' => $start->copy()->addDays(30),
                'created_by'        => $createdBy,
                'updated_by'        => $createdBy,
            ]);

            $this->recordEvent(
                $subscription->subscription_id,
                self::EVENT_CREATED,
                'Subscription created',
                $createdBy
            );

            $this->logEventOutbox(
                $tenantId,
                'subscriptions',
                $subscription->subscription_id,
                'SaasPlatform.SubscriptionCreated.v1',
                [
                    'subscription_id'  => $subscription->subscription_id,
                    'tenant_id'        => $tenantId,
                    'plan_version_id'  => $planVersionId,
                    'status'           => 1,
                    'start_date'       => $start->toIso8601String(),
                ]
            );

            return $subscription->load(['planVersion', 'events']);
        });
    }

    public function recordEvent(
        string $subscriptionId,
        int $eventType,
        ?string $description = null,
        ?string $createdBy = null
    ): SubscriptionEvent {
        return SubscriptionEvent::create([
            'subscription_id' => $subscriptionId,
            'event_type'      => $eventType,
            'description'     => $description,
            'event_date'      => Carbon::now(),
            'created_by'      => $createdBy,
            'updated_by'      => $createdBy,
        ]);
    }

    public function cancelSubscription(string $subscriptionId, ?string $cancelledBy = null): Subscription
    {
        return DB::transaction(function () use ($subscriptionId, $cancelledBy) {
            $subscription = Subscription::where('subscription_id', $subscriptionId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $subscription->status = 4;
            $subscription->end_date = Carbon::now();
            $subscription->updated_by = $cancelledBy;
            $subscription->save();

            $this->recordEvent(
                $subscriptionId,
                self::EVENT_CANCELLED,
                'Subscription cancelled',
                $cancelledBy
            );

            $this->logEventOutbox(
                $subscription->tenant_id,
                'subscriptions',
                $subscriptionId,
                'SaasPlatform.SubscriptionCancelled.v1',
                [
                    'subscription_id' => $subscriptionId,
                    'tenant_id'       => $subscription->tenant_id,
                    'status'          => 4,
                    'end_date'        => $subscription->end_date?->toIso8601String(),
                ]
            );

            return $subscription->fresh(['events']);
        });
    }

    public function updateSubscriptionStatus(
        string $subscriptionId,
        int $status,
        ?string $updatedBy = null
    ): Subscription {
        $subscription = Subscription::where('subscription_id', $subscriptionId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $subscription->status = $status;
        $subscription->updated_by = $updatedBy;
        $subscription->save();

        return $subscription->fresh();
    }

    public function softDeleteSubscription(string $subscriptionId, ?string $deletedBy = null): bool
    {
        $subscription = Subscription::where('subscription_id', $subscriptionId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $subscription->deleted_by = $deletedBy;
        $subscription->save();

        return (bool) $subscription->delete();
    }

    public function getActiveSubscription(string $tenantId): ?Subscription
    {
        return Subscription::with(['planVersion.plan', 'events'])
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->latest('start_date')
            ->first();
    }

    /**
     * Write integration event to shared outbox (same pattern as IdentityCore RoleService).
     * Event types are versioned for backward compatibility (law 6.4).
     */
    private function logEventOutbox(
        string $tenantId,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload
    ): void {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $tenantId,
            'aggregate_type' => $aggregateType,
            'aggregate_id'   => $aggregateId,
            'event_type'     => $eventType,
            'payload'        => json_encode($payload),
            'status'         => 1,
            'created_at'     => now(),
        ]);
    }
}