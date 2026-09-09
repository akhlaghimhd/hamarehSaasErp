<?php

namespace App\Modules\IdentityCore\Events;

/**
 * L4-M05 – Domain Event (versioned)
 * Emitted on logout.
 * Outbox event_type: identity.user.logged_out.v1
 */
class UserLoggedOutV1
{
    public const EVENT_TYPE = 'identity.user.logged_out.v1';

    public function __construct(
        public readonly string $userId,
        public readonly string $tenantId,
        public readonly ?string $occurredAt = null,
    ) {
        if ($this->occurredAt === null) {
            $this->occurredAt = now()->toIso8601String();
        }
    }

    public function toPayload(): array
    {
        return [
            'event_type'  => self::EVENT_TYPE,
            'user_id'     => $this->userId,
            'tenant_id'   => $this->tenantId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
