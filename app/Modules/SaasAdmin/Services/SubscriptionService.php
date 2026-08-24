<?php

namespace App\Modules\SaasAdmin\Services;

use App\Modules\SaasAdmin\Models\Subscription;
use App\Modules\SaasAdmin\Models\SubscriptionEvent;
use App\Modules\SaasAdmin\Models\PlanVersion;
use App\Modules\SaasAdmin\Models\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Carbon\Carbon;

class SubscriptionService
{
    // Event types (can be moved to Enum later)
    public const EVENT_CREATED = 1;
    public const EVENT_ACTIVATED = 2;
    public const EVENT_RENEWED = 3;
    public const EVENT_CANCELLED = 4;
    public const EVENT_EXPIRED = 5;
    public const EVENT_SUSPENDED = 6;

    /**
     * Create a new subscription for a tenant on a specific plan version.
     */
    public function createSubscription(
        string $tenantId,
        string $planVersionId,
        ?Carbon $startDate = null,
        ?string $createdBy = null
    ): Subscription {
        return DB::transaction(function () use ($tenantId, $planVersionId, $startDate, $createdBy) {
            // Validate tenant and plan version exist
            Tenant::where('tenant_id', $tenantId)->whereNull('deleted_at')->firstOrFail();
            PlanVersion::where('plan_version_id', $planVersionId)->whereNull('deleted_at')->firstOrFail();

            $start = $startDate ?? Carbon::now();

            $subscription = Subscription::create([
                'tenant_id'         => $tenantId,
                'plan_version_id'   => $planVersionId,
                'status'            => 1, // Active
                'start_date'        => $start,
                'next_billing_date' => $start->copy()->addDays(30), // default, will be refined later with price
                'created_by'        => $createdBy,
                'updated_by'        => $createdBy,
            ]);

            // Record creation event
            $this->recordEvent(
                $subscription->subscription_id,
                self::EVENT_CREATED,
                'Subscription created',
                $createdBy
            );

            return $subscription->load(['planVersion', 'events']);
        });
    }

    /**
     * Record a subscription event.
     */
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

    /**
     * Soft-cancel a subscription.
     */
    public function cancelSubscription(string $subscriptionId, ?string $cancelledBy = null): Subscription
    {
        return DB::transaction(function () use ($subscriptionId, $cancelledBy) {
            $subscription = Subscription::where('subscription_id', $subscriptionId)
                ->whereNull('deleted_at')
                ->firstOrFail();

            $subscription->status = 4; // Cancelled
            $subscription->end_date = Carbon::now();
            $subscription->updated_by = $cancelledBy;
            $subscription->save();

            $this->recordEvent(
                $subscriptionId,
                self::EVENT_CANCELLED,
                'Subscription cancelled',
                $cancelledBy
            );

            return $subscription->fresh(['events']);
        });
    }

    /**
     * Get active subscription for a tenant.
     */
    public function getActiveSubscription(string $tenantId): ?Subscription
    {
        return Subscription::with(['planVersion.plan', 'events'])
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->latest('start_date')
            ->first();
    }
}