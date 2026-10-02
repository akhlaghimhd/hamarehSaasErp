<?php

namespace App\Modules\IdentityCore\Services;

use App\Base\Support\TenantCache;
use App\Base\Http\Middleware\LoadUserScopesMiddleware;
use App\Modules\IdentityCore\Models\TenantPrivilegedGrant;
use App\Modules\IdentityCore\Models\TenantRole;
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
 * SoD: approver must not be the requester or the beneficiary (no self-approve).
 */
class PrivilegedAccessService
{
    public const MAX_DURATION_MINUTES = 480; // 8h hard cap

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

        return $q->get();
    }

    public function requestGrant(
        string $tenantId,
        string $userId,
        string $roleId,
        string $reason,
        int $durationMinutes = 60,
        ?string $requestedBy = null
    ): TenantPrivilegedGrant {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) < 5) {
            throw new HttpException(422, 'دلیل درخواست دسترسی ممتاز الزامی است (حداقل ۵ کاراکتر).');
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

        // One pending/active grant per user+role
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

    /**
     * Approve and immediately activate (time window starts now).
     * Approver must not be the requester or the beneficiary.
     */
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

            $this->assertNotSelfApprove($grant, $approverUserId);

            $starts = now();
            $ends = $starts->copy()->addMinutes((int) $grant->duration_minutes);

            $grant->status = TenantPrivilegedGrant::STATUS_ACTIVE;
            $grant->approved_by = $approverUserId;
            $grant->approved_at = $starts;
            $grant->starts_at = $starts;
            $grant->ends_at = $ends;
            $grant->row_version = ((int) $grant->row_version) + 1;
            $grant->save();

            $this->materializeRoleAssignment($tenantId, $grant->user_id, $grant->tenant_role_id);
            $this->forgetUserCaches($tenantId, $grant->user_id);

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
        $grant->row_version = ((int) $grant->row_version) + 1;
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
            $grant->row_version = ((int) $grant->row_version) + 1;
            $grant->save();

            $this->removeRoleAssignment($tenantId, $grant->user_id, $grant->tenant_role_id);
            $this->forgetUserCaches($tenantId, $grant->user_id);

            return $grant->fresh();
        });
    }

    /**
     * Expire ACTIVE grants past ends_at; returns number expired.
     */
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
                $grant->row_version = ((int) $grant->row_version) + 1;
                $grant->save();

                $this->removeRoleAssignment($tenantId, $grant->user_id, $grant->tenant_role_id);
                $this->forgetUserCaches($tenantId, $grant->user_id);
                $count++;
            });
        }

        return $count;
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
        $role->row_version = ((int) ($role->row_version ?? 1)) + 1;
        $role->save();

        return $role->fresh();
    }

    /**
     * Active privileged grants for a user at a point in time (for document audit correlation).
     *
     * @return list<TenantPrivilegedGrant>
     */
    public function activeGrantsForUserAt(
        string $tenantId,
        string $userId,
        $at = null
    ): array {
        $at = $at ? \Carbon\Carbon::parse($at) : now();

        return TenantPrivilegedGrant::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', TenantPrivilegedGrant::STATUS_ACTIVE)
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>', $at)
            ->whereNull('deleted_at')
            ->orderByDesc('starts_at')
            ->get()
            ->all();
    }

    private function assertNotSelfApprove(TenantPrivilegedGrant $grant, string $approverUserId): void
    {
        if ($approverUserId === (string) $grant->requested_by) {
            throw new HttpException(422, 'تأییدکننده نمی‌تواند همان درخواست‌کننده باشد (جلوگیری از self-approve).');
        }

        if ($approverUserId === (string) $grant->user_id) {
            throw new HttpException(422, 'ذینفع گرنت نمی‌تواند خودش آن را تأیید کند.');
        }
    }

    private function findGrant(string $tenantId, string $grantId): TenantPrivilegedGrant
    {
        $grant = TenantPrivilegedGrant::query()
            ->where('tenant_id', $tenantId)
            ->where('grant_id', $grantId)
            ->whereNull('deleted_at')
            ->first();

        if (!$grant) {
            throw new HttpException(404, 'گرنت دسترسی ممتاز یافت نشد.');
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
        try {
            TenantCache::forget('identity', "user_permissions:{$userId}", $tenantId);
            LoadUserScopesMiddleware::forget($tenantId, $userId);
        } catch (\Throwable $e) {
            // ignore cache backend issues in foundation
        }
    }
}
