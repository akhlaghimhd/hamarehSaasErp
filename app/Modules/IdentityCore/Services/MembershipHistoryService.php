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
 *
 * ID-W2-05 — Joiner / Mover / Leaver (JML) lifecycle helpers with typed events.
 */
class MembershipHistoryService
{
    /** Standard reason codes (competitive JML catalog). */
    public const REASON_JOINER = 'JOINER';
    public const REASON_MOVER = 'MOVER';
    public const REASON_LEAVER = 'LEAVER';
    public const REASON_STATUS_CHANGE = 'STATUS_CHANGE';
    public const REASON_SUSPEND = 'SUSPEND';
    public const REASON_REACTIVATE = 'REACTIVATE';
    public const REASON_SOFT_DELETE = 'SOFT_DELETE';

    public const STATUS_ACTIVE = 1;
    public const STATUS_SUSPENDED = 2;
    public const STATUS_INACTIVE = 0;

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

    public function listForTenant(?string $tenantUserId = null, int $limit = 100, ?string $reasonCode = null): Collection
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

        if ($reasonCode) {
            $query->where('reason_code', strtoupper($reasonCode));
        }

        return $this->withActorNames($query->get());
    }

    /**
     * Generic status transition recorder (kept for backward compatibility).
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

        TenantUser::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('tenant_user_id', $tenantUserId)
            ->firstOrFail();

        $reasonCode = $reasonCode ? strtoupper(trim($reasonCode)) : self::REASON_STATUS_CHANGE;

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

            $eventType = $this->eventTypeForReason($reasonCode);

            $this->logEventOutbox(
                $tenantId,
                'tenant_membership_histories',
                $history->history_id,
                $eventType,
                [
                    'history_id'      => $history->history_id,
                    'tenant_user_id'  => $tenantUserId,
                    'previous_status' => $previousStatus,
                    'new_status'      => $newStatus,
                    'reason_code'     => $reasonCode,
                    'lifecycle'       => $this->lifecycleLabel($reasonCode),
                ]
            );

            return $history;
        });
    }

    /** Joiner — new membership becomes active. */
    public function recordJoiner(
        string $tenantUserId,
        ?string $description = null,
        ?string $createdBy = null
    ): TenantMembershipHistory {
        return $this->recordChange(
            $tenantUserId,
            null,
            self::STATUS_ACTIVE,
            self::REASON_JOINER,
            $description ?? 'عضویت جدید (Joiner)',
            $createdBy
        );
    }

    /** Mover — role/scope/org change without leave (status stays active). */
    public function recordMover(
        string $tenantUserId,
        int $currentStatus = self::STATUS_ACTIVE,
        ?string $description = null,
        ?string $createdBy = null
    ): TenantMembershipHistory {
        return $this->recordChange(
            $tenantUserId,
            $currentStatus,
            $currentStatus,
            self::REASON_MOVER,
            $description ?? 'جابه‌جایی درون‌سازمانی (Mover)',
            $createdBy
        );
    }

    /** Leaver — membership ends (soft-delete or permanent exit). */
    public function recordLeaver(
        string $tenantUserId,
        ?int $previousStatus = self::STATUS_ACTIVE,
        ?string $description = null,
        ?string $createdBy = null
    ): TenantMembershipHistory {
        return $this->recordChange(
            $tenantUserId,
            $previousStatus,
            self::STATUS_INACTIVE,
            self::REASON_LEAVER,
            $description ?? 'خروج از سازمان (Leaver)',
            $createdBy
        );
    }

    public function recordSuspend(
        string $tenantUserId,
        ?int $previousStatus = self::STATUS_ACTIVE,
        ?string $description = null,
        ?string $createdBy = null
    ): TenantMembershipHistory {
        return $this->recordChange(
            $tenantUserId,
            $previousStatus,
            self::STATUS_SUSPENDED,
            self::REASON_SUSPEND,
            $description ?? 'تعلیق عضویت',
            $createdBy
        );
    }

    public function recordReactivate(
        string $tenantUserId,
        ?int $previousStatus = self::STATUS_SUSPENDED,
        ?string $description = null,
        ?string $createdBy = null
    ): TenantMembershipHistory {
        return $this->recordChange(
            $tenantUserId,
            $previousStatus,
            self::STATUS_ACTIVE,
            self::REASON_REACTIVATE,
            $description ?? 'فعال‌سازی مجدد عضویت',
            $createdBy
        );
    }

    /**
     * @return list<string>
     */
    public function knownReasonCodes(): array
    {
        return [
            self::REASON_JOINER,
            self::REASON_MOVER,
            self::REASON_LEAVER,
            self::REASON_STATUS_CHANGE,
            self::REASON_SUSPEND,
            self::REASON_REACTIVATE,
            self::REASON_SOFT_DELETE,
        ];
    }

    private function eventTypeForReason(string $reasonCode): string
    {
        return match ($reasonCode) {
            self::REASON_JOINER => 'identity.membership.joiner.v1',
            self::REASON_MOVER => 'identity.membership.mover.v1',
            self::REASON_LEAVER, self::REASON_SOFT_DELETE => 'identity.membership.leaver.v1',
            self::REASON_SUSPEND => 'identity.membership.suspended.v1',
            self::REASON_REACTIVATE => 'identity.membership.reactivated.v1',
            default => 'identity.membership_history.recorded.v1',
        };
    }

    private function lifecycleLabel(string $reasonCode): string
    {
        return match ($reasonCode) {
            self::REASON_JOINER => 'JOINER',
            self::REASON_MOVER => 'MOVER',
            self::REASON_LEAVER, self::REASON_SOFT_DELETE => 'LEAVER',
            self::REASON_SUSPEND => 'SUSPEND',
            self::REASON_REACTIVATE => 'REACTIVATE',
            default => 'STATUS_CHANGE',
        };
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
