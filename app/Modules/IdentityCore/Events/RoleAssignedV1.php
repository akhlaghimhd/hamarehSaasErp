<?php

namespace App\Modules\IdentityCore\Events;

/**
 * L4-M05 – Domain Event (versioned)
 * Emitted when a role is assigned to a user within a tenant.
 * Outbox event_type: identity.role.assigned.v1
 */
class RoleAssignedV1
{
    public const EVENT_TYPE = 'identity.role.assigned.v1';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $userId,
        public readonly string $tenantRoleId,
        public readonly ?string $assignedBy = null,
        public readonly ?string $occurredAt = null,
    ) {
        if ($this->occurredAt === null) {
            $this->occurredAt = now()->toIso8601String();
        }
    }

    public function toPayload(): array
    {
        return [
            'event_type'      => self::EVENT_TYPE,
            'tenant_id'       => $this->tenantId,
            'user_id'         => $this->userId,
            'tenant_role_id'  => $this->tenantRoleId,
            'assigned_by'     => $this->assignedBy,
            'occurred_at'     => $this->occurredAt,
        ];
    }
}
