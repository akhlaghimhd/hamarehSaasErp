<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\Models\TenantMembershipHistory;
use App\Modules\IdentityCore\Models\TenantUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

/**
 * Append-only audit of tenant membership status changes.
 * Read APIs + internal recordChange used by UserService.
 */
class MembershipHistoryService
{
    /**
     * List history rows for one tenant membership (current tenant only).
     */
    public function listByTenantUser(string $tenantUserId): Collection
    {
        $tenantId = $this->getTenantId();

        // Ensure membership belongs to current tenant (isolation).
        TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_user_id', $tenantUserId)
            ->firstOrFail();

        return TenantMembershipHistory::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_user_id', $tenantUserId)
            ->orderByDesc('effective_date')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * List recent membership history for the current tenant (optional filter by tenant_user_id).
     */
    public function listForTenant(?string $tenantUserId = null, int $limit = 100): Collection
    {
        $tenantId = $this->getTenantId();

        $query = TenantMembershipHistory::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('effective_date')
            ->orderByDesc('created_at')
            ->limit(max(1, min($limit, 500)));

        if ($tenantUserId) {
            $query->where('tenant_user_id', $tenantUserId);
        }

        return $query->get();
    }

    /**
     * Append a status-change history row (no update of existing rows).
     * Called from UserService when membership status changes or is soft-deleted.
     */
    public function recordChange(
        string $tenantUserId,
        ?int $previousStatus,
        int $newStatus,
        ?string $reasonCode = null,
        ?string $description = null,
        ?string $createdBy = null
    ): TenantMembershipHistory {
        $tenantId = $this->getTenantId();

        TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_user_id', $tenantUserId)
            ->firstOrFail();

        return DB::transaction(function () use (
            $tenantId,
            $tenantUserId,
            $previousStatus,
            $newStatus,
            $reasonCode,
            $description,
            $createdBy
        ) {
            $history = TenantMembershipHistory::create([
                'history_id'      => (string) Str::uuid(),
                'tenant_id'       => $tenantId,
                'tenant_user_id'  => $tenantUserId,
                'previous_status' => $previousStatus,
                'new_status'      => $newStatus,
                'reason_code'     => $reasonCode,
                'description'     => $description,
                'effective_date'  => now(),
                'created_by'      => $createdBy,
                'row_version'     => 1,
            ]);

            $this->logEventOutbox(
                $tenantId,
                'tenant_membership_histories',
                $history->history_id,
                'identity.membership_history.recorded.v1',
                [
                    'history_id'      => $history->history_id,
                    'tenant_user_id'  => $tenantUserId,
                    'previous_status' => $previousStatus,
                    'new_status'      => $newStatus,
                    'reason_code'     => $reasonCode,
                ]
            );

            return $history;
        });
    }

    private function getTenantId(): string
    {
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        if (!$tenantId) {
            throw new Exception('Tenant Context is missing. Architecture Violation.');
        }

        return $tenantId;
    }

    private function logEventOutbox(
        string $tenantId,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload
    ): void {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $tenantId,
            'aggregate_type' => $aggregateType,
            'aggregate_id'   => $aggregateId,
            'event_type'     => $eventType,
            'payload'        => json_encode($payload),
            'status'         => 1,
            'created_at'     => now(),
        ]);
    }
}
