<?php

namespace App\Base\Support;

/**
 * Shared audit payload for sensitive document / operational actions.
 *
 * Document modules (Accounting, Sales, Inventory, …) SHOULD persist these
 * fields on action audit rows so reports can prove whether the actor acted
 * under standing access or a privileged (break-glass) grant.
 *
 * Identity owns grant history; consumer modules own action rows.
 * No physical FK across modules — privileged_grant_id is a logical UUID.
 */
final class SensitiveActionAuditContext
{
    public const CHANNEL_STANDING = 'STANDING';
    public const CHANNEL_PRIVILEGED_GRANT = 'PRIVILEGED_GRANT';
    public const CHANNEL_OWNER = 'OWNER';
    public const CHANNEL_SYSTEM = 'SYSTEM';

    /**
     * @param  list<string>  $effectiveRoleIds
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $actorUserId,
        public readonly string $actedAtIso,
        public readonly string $actionCode,
        public readonly string $resourceType,
        public readonly string $resourceId,
        public readonly string $accessChannel,
        public readonly ?string $privilegedGrantId = null,
        public readonly array $effectiveRoleIds = [],
        public readonly ?string $permissionUsed = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'actor_user_id' => $this->actorUserId,
            'acted_at' => $this->actedAtIso,
            'action_code' => $this->actionCode,
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
            'access_channel' => $this->accessChannel,
            'privileged_grant_id' => $this->privilegedGrantId,
            'effective_role_ids' => array_values($this->effectiveRoleIds),
            'permission_used' => $this->permissionUsed,
        ];
    }
}
