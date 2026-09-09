<?php

namespace App\Modules\PartnerLayer\Events;

/**
 * L3 Domain Event (versioned)
 * Emitted when a Partner is soft-deleted.
 */
class PartnerDeletedV1
{
    public const EVENT_TYPE = 'PartnerLayer.PartnerDeleted.v1';

    public readonly string $occurredAt;

    public function __construct(
        public readonly string $partnerId,
        public readonly ?string $tenantId,
        public readonly string $code,
        public readonly ?string $deletedBy = null,
        ?string $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? now()->toIso8601String();
    }

    public function toPayload(): array
    {
        return [
            'event_type'  => self::EVENT_TYPE,
            'partner_id'  => $this->partnerId,
            'tenant_id'   => $this->tenantId,
            'code'        => $this->code,
            'deleted_by'  => $this->deletedBy,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
