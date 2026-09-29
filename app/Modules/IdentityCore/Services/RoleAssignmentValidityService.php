<?php

namespace App\Modules\IdentityCore\Services;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ID-W3-01 — Effective (time-valid) role ids for a user + validity helpers.
 */
class RoleAssignmentValidityService
{
    /**
     * Role ids currently effective for the user (respects valid_from / valid_to).
     *
     * @return list<string>
     */
    public function effectiveRoleIds(string $tenantId, string $userId, ?\DateTimeInterface $at = null): array
    {
        $at = $at ?? now();

        return DB::table('tenant_user_roles')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($at) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>', $at);
            })
            ->pluck('tenant_role_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Soft-delete assignments whose valid_to is in the past.
     * Returns number of rows expired.
     */
    public function expireStale(string $tenantId, ?\DateTimeInterface $at = null): int
    {
        $at = $at ?? now();

        return DB::table('tenant_user_roles')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotNull('valid_to')
            ->where('valid_to', '<=', $at)
            ->update([
                'deleted_at' => $at,
                'updated_at' => $at,
            ]);
    }

    public function assertValidWindow(?string $validFrom, ?string $validTo): void
    {
        if ($validFrom === null && $validTo === null) {
            return;
        }

        $from = $validFrom ? \Carbon\Carbon::parse($validFrom) : null;
        $to = $validTo ? \Carbon\Carbon::parse($validTo) : null;

        if ($from && $to && $to->lte($from)) {
            throw new HttpException(422, 'valid_to باید بعد از valid_from باشد.');
        }
    }
}
