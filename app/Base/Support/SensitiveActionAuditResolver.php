<?php

namespace App\Base\Support;

use App\Modules\IdentityCore\Services\PrivilegedAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds SensitiveActionAuditContext for a sensitive action at "now" (or $at).
 *
 * Resolution order for access_channel:
 * 1) ACTIVE privileged grant covering the actor → PRIVILEGED_GRANT (+ grant_id)
 * 2) tenant owner membership → OWNER
 * 3) otherwise → STANDING
 *
 * SYSTEM is reserved for non-user automated actors (pass channel explicitly).
 */
final class SensitiveActionAuditResolver
{
    public function __construct(
        private readonly PrivilegedAccessService $privilegedAccess
    ) {
    }

    /**
     * @param  list<string>|null  $effectiveRoleIds  if null, loads current tenant_user_roles
     */
    public function resolve(
        string $tenantId,
        string $actorUserId,
        string $actionCode,
        string $resourceType,
        string $resourceId,
        ?string $permissionUsed = null,
        $at = null,
        ?array $effectiveRoleIds = null,
        ?string $forcedChannel = null,
    ): SensitiveActionAuditContext {
        $atCarbon = $at ? \Carbon\Carbon::parse($at) : now();
        $actedAtIso = $atCarbon->toIso8601String();

        if ($forcedChannel === SensitiveActionAuditContext::CHANNEL_SYSTEM) {
            return new SensitiveActionAuditContext(
                tenantId: $tenantId,
                actorUserId: $actorUserId,
                actedAtIso: $actedAtIso,
                actionCode: $actionCode,
                resourceType: $resourceType,
                resourceId: $resourceId,
                accessChannel: SensitiveActionAuditContext::CHANNEL_SYSTEM,
                privilegedGrantId: null,
                effectiveRoleIds: $effectiveRoleIds ?? [],
                permissionUsed: $permissionUsed,
            );
        }

        $grants = $this->privilegedAccess->activeGrantsForUserAt($tenantId, $actorUserId, $atCarbon);
        $grantId = null;
        $channel = SensitiveActionAuditContext::CHANNEL_STANDING;

        if ($grants !== []) {
            $channel = SensitiveActionAuditContext::CHANNEL_PRIVILEGED_GRANT;
            $grantId = (string) $grants[0]->grant_id;
        } elseif ($this->isOwner($tenantId, $actorUserId)) {
            $channel = SensitiveActionAuditContext::CHANNEL_OWNER;
        }

        $roles = $effectiveRoleIds ?? $this->loadRoleIds($tenantId, $actorUserId);

        return new SensitiveActionAuditContext(
            tenantId: $tenantId,
            actorUserId: $actorUserId,
            actedAtIso: $actedAtIso,
            actionCode: $actionCode,
            resourceType: $resourceType,
            resourceId: $resourceId,
            accessChannel: $channel,
            privilegedGrantId: $grantId,
            effectiveRoleIds: $roles,
            permissionUsed: $permissionUsed,
        );
    }

    private function isOwner(string $tenantId, string $userId): bool
    {
        if (!Schema::hasTable('tenant_users')) {
            return false;
        }

        return DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('is_owner', true)
            ->whereNull('deleted_at')
            ->exists();
    }

    /** @return list<string> */
    private function loadRoleIds(string $tenantId, string $userId): array
    {
        if (!Schema::hasTable('tenant_user_roles')) {
            return [];
        }

        return DB::table('tenant_user_roles')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->pluck('tenant_role_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }
}
