<?php

namespace App\Modules\SaasAdmin\Events;

/**
 * L2-M07 – Domain Event (versioned)
 * Emitted when a new platform admin user is created.
 */
class AdminUserCreatedV1
{
    public const EVENT_TYPE = 'AdminUserCreated.v1';

    public function __construct(
        public readonly string $adminUserId,
        public readonly string $username,
        public readonly string $email,
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
            'admin_user_id' => $this->adminUserId,
            'username'      => $this->username,
            'email'         => $this->email,
            'created_by'    => $this->createdBy,
            'occurred_at'   => $this->occurredAt,
        ];
    }
}
