<?php

namespace App\Modules\SaasPlatform\Events;

use App\Modules\SaasPlatform\Models\Tenant;

/**
 * Domain event — tenant created.
 * Event type string: saas.tenant.created.v1
 */
final class TenantCreatedV1
{
    public const EVENT_TYPE = 'saas.tenant.created.v1';
    public const AGGREGATE_TYPE = 'tenants';

    /**
     * @return array<string, mixed>
     */
    public static function payload(Tenant $tenant): array
    {
        return [
            'event'        => self::EVENT_TYPE,
            'tenant_id'    => $tenant->tenant_id,
            'tenant_code'  => $tenant->tenant_code,
            'tenant_name'  => $tenant->tenant_name,
            'slug'         => $tenant->slug,
            'status'       => (int) $tenant->status,
            'tenant_type'  => (int) $tenant->tenant_type,
        ];
    }
}
