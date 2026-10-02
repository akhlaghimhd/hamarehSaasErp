<?php

namespace App\Modules\IdentityCore\Services;

use App\Base\Support\TenantCache;
use App\Base\Http\Middleware\LoadUserScopesMiddleware;
use App\Modules\IdentityCore\Models\TenantPrivilegedGrant;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantUserRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ID-W2-02 — Privileged / Emergency access (break-glass).
 *
 * Flow: request → approve+activate (time-boxed) → auto-expire or revoke.
 * Active grants materialize as tenant_user_roles rows for the duration;
 * revoke/expire removes that assignment.
 *
 * SoD: non-owner may not approve a grant where they are the beneficiary (user_id).
 * Requesting a grant for another user and then approving it is allowed (admin workflow).
 * Tenant owners (is_owner) may approve any pending grant including their own.
 */
class PrivilegedAccessService
{
    public const MAX_DURATION_MINUTES = 43200; // 30 days (presets: 1h / 8h / 7d / 30d)

    public function listGrants(string $tenantId, ?string $status = null): Collection
    {
        $this->expireStale($tenantId);

        $q = TenantPrivilegedGrant::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at');

        if ($status) {
            $q->where('status', strtoupper($status));
        }

        $grants = $q->get();

        $userIds = $grants->pluck('user_id')->filter()->unique()->values()->all();
        $usersById = [];
        if ($userIds !== []) {
            $rows = DB::table('users')
                ->whereIn('user_id', $userIds)
                ->get(['user_id', 'first_name', 'last_name', 'email', 'mobile']);
            foreach ($rows as $row) {
                $name = trim(implode(' ', array_filter([(string) ($row->first_name ?? ''), (string) ($row->last_name ?? '')])));
                $usersById[(string) $row->user_id] = [
                    'user_display_name' => $name !== '' ? $name : (string) ($row->email ?? $row->mobile ?? ''),
                    'user_mobile'       => $row->mobile,
                    'user_email'        => $row->email,
                ];
            }
        }

        foreach ($grants as $grant) {
            $info = $usersById[(string) $grant->user_id] ?? null;
            if ($info) {
                $grant->setAttribute('user_display_name', $info['user_display_name']);
                $grant->setAttribute('user_mobile', $info['user_mobile']);
                $grant->setAttribute('user_email', $info['user_email']);
            }
        }

        return $grants;
    }

    public function requestGrant(
        string $tenantId,
        string $userId,
        string $roleId,
        string $reason,
        int $durationMinutes = 60,
        ?string $requestedBy = null
    ): TenantPrivilegedGrant {
        if (strlen(trim($reason)) < 5) {
            throw new HttpException(422, 'دلیل درخواست الزامی است (حداقل ۵ کاراکتر).');
        }

        $durationMinutes = max(5, min($durationMinutes, self::MAX_DURATION_MINUTES));

        $role = TenantRole::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_role_id', $roleId)
            ->whereNull('deleted_at')
            ->first();

        if (!$role) {
            throw new HttpException(404, 'نقش یافت نشد.');
        }

        if (!(bool) ($role->is_privileged ?? false)) {
            throw new HttpException(422, 'این نقش به‌عنوان نقش ممتاز (privileged) علامت‌گذاری نشده است.');
        }

        $dup = TenantPrivilegedGrant::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('tenant_role_id', $roleId)
            ->whereIn('status', [TenantPrivilegedGrant::STATUS_PENDING, TenantPrivilegedGrant::STATUS_ACTIVE])
            ->whereNull('deleted_at')
            ->exists();

        if ($dup) {
            throw new HttpException(422, 'برای این کاربر و نقش، درخواست/گرنت فعال یا در انتظار از قبل وجود دارد.');
        }

        return TenantPrivilegedGrant::create([
            'grant_id'          => (string) Str::uuid(),
            'tenant_id'         => $tenantId,
            'user_id'           => $userId,
            'tenant_role_id'    => $roleId,
            'reason'            => $reason,
            'status'            => TenantPrivilegedGrant::STATUS_PENDING,
            'duration_minutes'  => $durationMinutes,
            'requested_by'      => $requestedBy ?? $userId,
            'created_by'        => $requestedBy,
            'row_version'       => 1,
        ]);
    }

    public function approveAndActivate(
        string $tenantId,
        string $grantId,
        string $approverUserId
    ): TenantPrivilegedGrant {
        return DB::transaction(function () use ($tenantId, $grantId, $approverUserId) {
            $grant = $this->findGrant($tenantId, $grantId);

            if ($grant->status !== TenantPrivilegedGrant::STATUS_PENDING) {
                throw new HttpException(422, 'فقط درخواست PENDING قابل تأیید است.');
            }

            $this->assertNotSelfApprove($grant, $approverUserId, $tenantId);

            $starts = now();
            $ends = $starts->copy()->addMinutes((int) $grant->duration_minutes);

            $grant->status = TenantPrivilegedGrant::STATUS_ACTIVE;
            $grant->starts_at = $starts;
            $grant->ends_at = $ends;
            $grant->approved_by = $approverUserId;
            $grant->approved_at = $starts;
            $grant->updated_by = $approverUserId;
            $grant->row_version = (int) $grant->row_version + 1;
            $grant->save();

            $this->materializeRoleAssignment($tenantId, (string) $grant->user_id, (string) $grant->tenant_role_id);
            $this->forgetUserCaches($tenantId, (string) $grant->user_id);

            return $grant->fresh();
        });
    }

    public function deny(string $tenantId, string $grantId, string $actorId, ?string $note = null): TenantPrivilegedGrant
    {
        $grant = $this->findGrant($tenantId, $grantId);

        if ($grant->status !== TenantPrivilegedGrant::STATUS_PENDING) {
            throw new HttpException(422, 'فقط درخواست PENDING قابل رد است.');
        }

        $grant->status = TenantPrivilegedGrant::STATUS_DENIED;
        $grant->revoked_by = $actorId;
        $grant->revoked_at = now();
        $grant->revoke_reason = $note;
        $grant->updated_by = $actorId;
        $grant->row_version = (int) $grant->row_version + 1;
        $grant->save();

        return $grant->fresh();
    }

    public function revoke(
        string $tenantId,
        string $grantId,
        string $actorId,
        ?string $reason = null
    ): TenantPrivilegedGrant {
        return DB::transaction(function () use ($tenantId, $grantId, $actorId, $reason) {
            $grant = $this->findGrant($tenantId, $grantId);

            if ($grant->status !== TenantPrivilegedGrant::STATUS_ACTIVE) {
                throw new HttpException(422, 'فقط گرنت ACTIVE قابل لغو است.');
            }

            $grant->status = TenantPrivilegedGrant::STATUS_REVOKED;
            $grant->revoked_by = $actorId;
            $grant->revoked_at = now();
            $grant->revoke_reason = $reason;
            $grant->updated_by = $actorId;
            $grant->row_version = (int) $grant->row_version + 1;
            $grant->save();

            $this->removeRoleAssignment($tenantId, (string) $grant->user_id, (string) $grant->tenant_role_id);
            $this->forgetUserCaches($tenantId, (string) $grant->user_id);

            return $grant->fresh();
        });
    }

    public function expireStale(string $tenantId): int
    {
        $stale = TenantPrivilegedGrant::query()
            ->where('tenant_id', $tenantId)
            ->where('status', TenantPrivilegedGrant::STATUS_ACTIVE)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->whereNull('deleted_at')
            ->get();

        $count = 0;
        foreach ($stale as $grant) {
            DB::transaction(function () use ($grant, $tenantId, &$count) {
                $grant->status = TenantPrivilegedGrant::STATUS_EXPIRED;
                $grant->row_version = (int) $grant->row_version + 1;
                $grant->save();
                $this->removeRoleAssignment($tenantId, (string) $grant->user_id, (string) $grant->tenant_role_id);
                $this->forgetUserCaches($tenantId, (string) $grant->user_id);
                $count++;
            });
        }

        return $count;
    }

    /**
     * Extend an ACTIVE grant: ends_at moves forward from max(now, ends_at).
     * duration_minutes accumulates the additional window.
     */
    public function extend(
        string $tenantId,
        string $grantId,
        int $additionalMinutes,
        string $actorId
    ): TenantPrivilegedGrant {
        $additionalMinutes = max(5, min($additionalMinutes, self::MAX_DURATION_MINUTES));

        return DB::transaction(function () use ($tenantId, $grantId, $additionalMinutes, $actorId) {
            $grant = $this->findGrant($tenantId, $grantId);

            if ($grant->status !== TenantPrivilegedGrant::STATUS_ACTIVE) {
                throw new HttpException(422, 'فقط گرنت ACTIVE قابل تمدید است.');
            }

            $base = $grant->ends_at && $grant->ends_at->greaterThan(now())
                ? $grant->ends_at->copy()
                : now();

            $grant->ends_at = $base->addMinutes($additionalMinutes);
            $grant->duration_minutes = (int) $grant->duration_minutes + $additionalMinutes;
            $grant->updated_by = $actorId;
            $grant->row_version = (int) $grant->row_version + 1;
            $grant->save();

            $this->materializeRoleAssignment($tenantId, (string) $grant->user_id, (string) $grant->tenant_role_id);
            $this->forgetUserCaches($tenantId, (string) $grant->user_id);

            return $grant->fresh();
        });
    }

    public function markRolePrivileged(string $tenantId, string $roleId, bool $flag = true): TenantRole
    {
        $role = TenantRole::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_role_id', $roleId)
            ->whereNull('deleted_at')
            ->first();

        if (!$role) {
            throw new HttpException(404, 'نقش یافت نشد.');
        }

        $role->is_privileged = $flag;
        $role->row_version = (int) $role->row_version + 1;
        $role->save();

        TenantCache::flushTenant($tenantId);

        return $role->fresh();
    }

    /**
     * Active privileged grants for a user at a point in time (audit / reports).
     */
    public function activeGrantsForUserAt(
        string $tenantId,
        string $userId,
        $at = null
    ): Collection {
        $at = $at ?? now();

        return TenantPrivilegedGrant::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', TenantPrivilegedGrant::STATUS_ACTIVE)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($at) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', $at);
            })
            ->get();
    }

    private function assertNotSelfApprove(
        TenantPrivilegedGrant $grant,
        string $approverUserId,
        string $tenantId
    ): void {
        // Only block when the approver is the beneficiary (self-grant).
        // Requesting for another user and approving is a normal admin workflow.
        if ($approverUserId !== (string) $grant->user_id) {
            return;
        }

        if ($this->isTenantOwner($tenantId, $approverUserId)) {
            return;
        }

        throw new HttpException(
            422,
            'تأیید دسترسی اضطراری برای خودتان مجاز نیست (مگر مالک سازمان).'
        );
    }

    private function isTenantOwner(string $tenantId, string $userId): bool
    {
        return TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('is_owner', true)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->exists();
    }

    private function findGrant(string $tenantId, string $grantId): TenantPrivilegedGrant
    {
        $grant = TenantPrivilegedGrant::query()
            ->where('tenant_id', $tenantId)
            ->where('grant_id', $grantId)
            ->whereNull('deleted_at')
            ->first();

        if (!$grant) {
            throw new HttpException(404, 'گرنت یافت نشد.');
        }

        return $grant;
    }

    private function materializeRoleAssignment(string $tenantId, string $userId, string $roleId): void
    {
        $exists = TenantUserRole::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('tenant_role_id', $roleId)
            ->exists();

        if ($exists) {
            return;
        }

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $tenantId,
            'user_id'             => $userId,
            'tenant_role_id'      => $roleId,
        ]);
    }

    private function removeRoleAssignment(string $tenantId, string $userId, string $roleId): void
    {
        TenantUserRole::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('tenant_role_id', $roleId)
            ->delete();
    }

    private function forgetUserCaches(string $tenantId, string $userId): void
    {
        TenantCache::flushTenant($tenantId);
        LoadUserScopesMiddleware::forget($tenantId, $userId);
    }
}
