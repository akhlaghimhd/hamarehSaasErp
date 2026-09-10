<?php

namespace App\Modules\MasterData\Events;

/**
 * L5 – Domain Event (versioned)
 * Emitted when a new Business Partner is created.
 * Outbox event_type: master_data.business_partner.created.v1
 */
class BusinessPartnerCreatedV1
{
    public const EVENT_TYPE = 'master_data.business_partner.created.v1';

    public readonly string $occurredAt;

    public function __construct(
        public readonly string $businessPartnerId,
        public readonly string $tenantId,
        public readonly string $code,
        public readonly string $displayName,
        public readonly int $partnerType,
        public readonly ?string $createdBy = null,
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
            'display_name'         => $this->displayName,
            'partner_type'         => $this->partnerType,
            'created_by'           => $this->createdBy,
            'occurred_at'          => $this->occurredAt,
        ];
    }
}
