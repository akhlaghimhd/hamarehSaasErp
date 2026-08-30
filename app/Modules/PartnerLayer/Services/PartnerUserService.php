<?php

namespace App\Modules\PartnerLayer\Services;

use App\Modules\PartnerLayer\Models\Partner;
use App\Modules\PartnerLayer\Models\PartnerUser;
use App\Modules\PartnerLayer\DTOs\CreatePartnerUserDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerUserDTO;
use App\Base\Context\TenantContext;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * P3-S2 — PartnerUser application service.
 *
 * Links IdentityCore user_id (logical reference, no cross-module FK)
 * to a Partner. Enforces partner accessibility in current tenant context
 * and unique (partner_id, user_id) while soft-deleted rows are ignored.
 */
class PartnerUserService
{
    public function getPartnerUsers(?string $partnerId = null): Collection
    {
        $query = PartnerUser::query()->orderBy('created_at', 'desc');

        if ($partnerId) {
            $this->assertPartnerAccessible($partnerId);
            $query->where('partner_id', $partnerId);
        } else {
            $accessiblePartnerIds = $this->accessiblePartnerIds();
            if ($accessiblePartnerIds->isEmpty()) {
                return collect();
            }
            $query->whereIn('partner_id', $accessiblePartnerIds);
        }

        return $query->get();
    }

    public function getPartnerUserById(string $partnerUserId): PartnerUser
    {
        $partnerUser = PartnerUser::query()
            ->where('partner_user_id', $partnerUserId)
            ->firstOrFail();

        $this->assertPartnerAccessible($partnerUser->partner_id);

        return $partnerUser;
    }

    public function createPartnerUser(CreatePartnerUserDTO $dto): PartnerUser
    {
        $this->assertPartnerAccessible($dto->partnerId);

        $exists = PartnerUser::query()
            ->where('partner_id', $dto->partnerId)
            ->where('user_id', $dto->userId)
            ->exists();

        if ($exists) {
            throw new Exception('This user is already linked to the partner.');
        }

        return DB::transaction(function () use ($dto) {
            if ($dto->isPrimary) {
                $this->clearPrimaryForPartner($dto->partnerId);
            }

            return PartnerUser::create([
                'partner_id' => $dto->partnerId,
                'user_id'    => $dto->userId,
                'is_primary' => $dto->isPrimary,
                'status'     => $dto->status,
            ]);
        });
    }

    public function updatePartnerUser(string $partnerUserId, UpdatePartnerUserDTO $dto): PartnerUser
    {
        $partnerUser = $this->getPartnerUserById($partnerUserId);

        return DB::transaction(function () use ($partnerUser, $dto) {
            if ($dto->isPrimary === true) {
                $this->clearPrimaryForPartner($partnerUser->partner_id, $partnerUser->partner_user_id);
            }

            $partnerUser->update([
                'is_primary' => $dto->isPrimary ?? $partnerUser->is_primary,
                'status'     => $dto->status ?? $partnerUser->status,
            ]);

            return $partnerUser->fresh();
        });
    }

    public function deletePartnerUser(string $partnerUserId): void
    {
        $partnerUser = $this->getPartnerUserById($partnerUserId);
        $partnerUser->delete();
    }

    private function assertPartnerAccessible(string $partnerId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $query = Partner::query()->where('partner_id', $partnerId);

        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhereNull('tenant_id');
            });
        }

        if (!$query->exists()) {
            throw new Exception('Partner not found or not accessible in this context.');
        }
    }

    private function accessiblePartnerIds(): Collection
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $query = Partner::query()->select('partner_id');

        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhereNull('tenant_id');
            });
        }

        return $query->pluck('partner_id');
    }

    private function clearPrimaryForPartner(string $partnerId, ?string $exceptPartnerUserId = null): void
    {
        $query = PartnerUser::query()
            ->where('partner_id', $partnerId)
            ->where('is_primary', true);

        if ($exceptPartnerUserId) {
            $query->where('partner_user_id', '!=', $exceptPartnerUserId);
        }

        $query->update(['is_primary' => false]);
    }
}
