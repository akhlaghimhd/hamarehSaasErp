<?php

namespace App\Modules\SaasAdmin\Events;

/**
 * L2-M07 – Domain Event (versioned)
 * Emitted when a new support ticket is created.
 */
class SupportTicketCreatedV1
{
    public const EVENT_TYPE = 'SupportTicketCreated.v1';

    public function __construct(
        public readonly string $ticketId,
        public readonly string $tenantId,
        public readonly string $ticketNumber,
        public readonly string $subject,
        public readonly ?string $createdBy = null,
        public readonly string $occurredAt = '',
    ) {
        if ($this->occurredAt === '') {
            $this->occurredAt = now()->toIso8601String();
        }
    }

    public function toPayload(): array
    {
        return [
            'event_type'    => self::EVENT_TYPE,
            'ticket_id'     => $this->ticketId,
            'tenant_id'     => $this->tenantId,
            'ticket_number' => $this->ticketNumber,
            'subject'       => $this->subject,
            'created_by'    => $this->createdBy,
            'occurred_at'   => $this->occurredAt,
        ];
    }
}
