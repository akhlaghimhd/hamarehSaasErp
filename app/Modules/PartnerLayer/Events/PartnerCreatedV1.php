<?php

namespace App\Modules\PartnerLayer\Events;

/**
 * L3 Domain Event (versioned)
 * Emitted when a new Partner is created.
 */
class PartnerCreatedV1
{
    public const EVENT_TYPE = 'PartnerLayer.PartnerCreated.v1';

    public readonly string $occurredAt;

    public function __construct(
        public readonly string $partnerId,
        public readonly ?string $tenantId,
        public readonly string $code,
        public readonly string $name,
        public readonly int $partnerType,
        public readonly int $status,
        public readonly ?string $createdBy = null,
        ?string $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? now()->toIso8601String();
    }

    public function toPayload(): array
    {
        return [
            'event_type'   => self::EVENT_TYPE,
            'partner_id'   => $this->partnerId,
            'tenant_id'    => $this->tenantId,
            'code'         => $this->code,
            'name'         => $this->name,
            'partner_type' => $this->partnerType,
            'status'       => $this->status,
            'created_by'   => $this->createdBy,
            'occurred_at'  => $this->occurredAt,
        ];
    }
}
