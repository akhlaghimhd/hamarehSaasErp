<?php

namespace App\Modules\MasterData\Events;

/**
 * L5 – Domain Event (versioned)
 * Emitted when a Business Partner is updated.
 * Outbox event_type: master_data.business_partner.updated.v1
 */
class BusinessPartnerUpdatedV1
{
    public const EVENT_TYPE = 'master_data.business_partner.updated.v1';

    public readonly string $occurredAt;

    public function __construct(
        public readonly string $businessPartnerId,
        public readonly string $tenantId,
        public readonly string $code,
        public readonly string $displayName,
        public readonly int $partnerType,
        public readonly int $status,
        public readonly ?string $updatedBy = null,
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
            'status'               => $this->status,
            'updated_by'           => $this->updatedBy,
            'occurred_at'          => $this->occurredAt,
        ];
    }
}
