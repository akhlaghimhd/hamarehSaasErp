<?php

namespace App\Modules\MasterData\Events;

/**
 * L5 – Domain Event (versioned)
 * Emitted when a Business Partner is soft-deleted.
 * Outbox event_type: master_data.business_partner.deleted.v1
 */
class BusinessPartnerDeletedV1
{
    public const EVENT_TYPE = 'master_data.business_partner.deleted.v1';

    public readonly string $occurredAt;

    public function __construct(
        public readonly string $businessPartnerId,
        public readonly string $tenantId,
        public readonly string $code,
        public readonly ?string $deletedBy = null,
        ?string $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? now()->toIso8601String();
    }

    public function toPayload(): array
    {
        return [
            'event_type'           => self::EVENT_TYPE,
            'business_partner_id'  => $this->businessPartnerId,
            'tenant_id'            => $this->tenantId,
            'code'                 => $this->code,
            'deleted_by'           => $this->deletedBy,
            'occurred_at'          => $this->occurredAt,
        ];
    }
}
