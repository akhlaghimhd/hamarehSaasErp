<?php

namespace App\Modules\IdentityCore\Events;

/**
 * L4-M05 – Domain Event (versioned)
 * Emitted on successful tenant-scoped login.
 * Outbox event_type: identity.user.logged_in.v1
 */
class UserLoggedInV1
{
    public const EVENT_TYPE = 'identity.user.logged_in.v1';

    public readonly string $occurredAt;

    public function __construct(
        public readonly string $userId,
        public readonly string $tenantId,
        ?string $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? now()->toIso8601String();
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
