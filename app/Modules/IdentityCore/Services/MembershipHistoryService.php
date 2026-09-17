<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\Models\TenantMembershipHistory;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

/**
 * Append-only audit of tenant membership changes.
 */
class MembershipHistoryService
{
    public function listByTenantUser(string $tenantUserId): Collection
    {
        $tenantId = $this->getTenantId();

        TenantUser::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('tenant_user_id', $tenantUserId)
            ->firstOrFail();

        $rows = TenantMembershipHistory::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_user_id', $tenantUserId)
            ->orderByDesc('effective_date')
            ->orderByDesc('created_at')
            ->get();

        return $this->withActorNames($rows);
    }

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

        return $this->withActorNames($query->get());
    }

    public function recordChange(
        string $tenantUserId,
        ?int $previousStatus,
        int $newStatus,
        ?string $reasonCode = null,
        ?string $description = null,
        ?string $createdBy = null
    ): TenantMembershipHistory {
        $tenantId = $this->getTenantId();

        TenantUser::withTrashed()
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

    /**
     * @param  Collection<int, TenantMembershipHistory>  $rows
     * @return Collection<int, TenantMembershipHistory>
     */
    private function withActorNames(Collection $rows): Collection
    {
        $ids = $rows->pluck('created_by')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return $rows->each(function (TenantMembershipHistory $row) {
                $row->setAttribute('actor_name', null);
            });
        }

        $users = User::query()
            ->whereIn('user_id', $ids)
            ->get(['user_id', 'first_name', 'last_name', 'email'])
            ->keyBy('user_id');

        return $rows->each(function (TenantMembershipHistory $row) use ($users) {
            $actor = $row->created_by ? $users->get($row->created_by) : null;
            if (!$actor) {
                $row->setAttribute('actor_name', null);
                return;
            }
            $name = trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? ''));
            $row->setAttribute('actor_name', $name !== '' ? $name : ($actor->email ?? null));
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
