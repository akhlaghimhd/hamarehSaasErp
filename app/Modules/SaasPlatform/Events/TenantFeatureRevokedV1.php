<?php

namespace App\Modules\SaasPlatform\Events;

/**
 * Domain event — feature pack revoked / disabled (freeze) for a tenant.
 * Event type string: saas.tenant_feature.revoked.v1
 *
 * Consumers (e.g. Hierarchy D1) must hide/freeze structural UX — never hard-delete operational data.
 */
final class TenantFeatureRevokedV1
{
    public const EVENT_TYPE = 'saas.tenant_feature.revoked.v1';
    public const AGGREGATE_TYPE = 'tenant_feature_entitlements';

    /**
     * @return array<string, mixed>
     */
    public static function payload(
        string $tenantId,
        string $featureCode,
        string $entitlementId,
        string $source,
        ?string $notes = null
    ): array {
        return [
            'event'          => self::EVENT_TYPE,
            'tenant_id'      => $tenantId,
            'feature_code'   => $featureCode,
            'entitlement_id' => $entitlementId,
            'source'         => $source,
            'notes'          => $notes,
            'is_enabled'     => false,
        ];
    }
}
