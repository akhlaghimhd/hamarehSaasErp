<?php

namespace App\Modules\SaasPlatform\Events;

use App\Modules\SaasPlatform\Models\Subscription;

/**
 * Domain event — subscription cancelled.
 * Event type string: saas.subscription.cancelled.v1
 */
final class SubscriptionCancelledV1
{
    public const EVENT_TYPE = 'saas.subscription.cancelled.v1';
    public const AGGREGATE_TYPE = 'subscriptions';

    /**
     * @return array<string, mixed>
     */
    public static function payload(Subscription $subscription): array
    {
        return [
            'event'            => self::EVENT_TYPE,
            'tenant_id'        => $subscription->tenant_id,
            'subscription_id'  => $subscription->subscription_id,
            'plan_version_id'  => $subscription->plan_version_id,
            'status'           => (int) $subscription->status,
            'end_date'         => $subscription->end_date?->toIso8601String(),
        ];
    }
}
