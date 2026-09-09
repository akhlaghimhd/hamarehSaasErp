<?php

namespace App\Modules\IdentityCore\Events;

/**
 * L4-M05 – Domain Event (versioned)
 * Emitted when a new platform user is registered.
 * Outbox event_type: identity.user.created.v1
 */
class UserCreatedV1
{
    public const EVENT_TYPE = 'identity.user.created.v1';

    public function __construct(
        public readonly string $userId,
        public readonly ?string $email = null,
        public readonly ?string $createdBy = null,
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
            'email'       => $this->email,
            'created_by'  => $this->createdBy,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
