<?php

namespace App\Modules\IdentityCore\Events;

/**
 * L4-M05 – Domain Event (versioned)
 * Emitted when a scope is assigned to a tenant user.
 * Outbox event_type: identity.scope.assigned.v1
 */
class ScopeAssignedV1
{
    public const EVENT_TYPE = 'identity.scope.assigned.v1';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $tenantUserId,
        public readonly string $scopeId,
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
            'tenant_user_id'  => $this->tenantUserId,
            'scope_id'        => $this->scopeId,
            'assigned_by'     => $this->assignedBy,
            'occurred_at'     => $this->occurredAt,
        ];
    }
}
