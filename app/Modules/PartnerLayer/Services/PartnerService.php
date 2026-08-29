<?php

namespace App\Modules\PartnerLayer\Services;

use App\Modules\PartnerLayer\Models\Partner;
use App\Modules\PartnerLayer\DTOs\CreatePartnerDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerDTO;
use App\Base\Context\TenantContext;
use Exception;
use Illuminate\Support\Collection;

/**
 * P3-S1 — Core Partner CRUD within PartnerLayer.
 *
 * tenant_id is taken from TenantContext for tenant-scoped partners.
 * Code uniqueness follows SSOT partial unique (tenant context + soft delete).
 */
class PartnerService
{
    public function getAllPartners(): Collection
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $query = Partner::query()->orderBy('created_at', 'desc');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get();
    }

    public function getPartnerById(string $partnerId): Partner
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $query = Partner::query()->where('partner_id', $partnerId);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->firstOrFail();
    }

    public function createPartner(CreatePartnerDTO $dto): Partner
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $this->assertCodeAvailable($dto->code, $tenantId);

        if ($dto->parentPartnerId) {
            $this->assertParentBelongsToTenant($dto->parentPartnerId, $tenantId);
        }

        $parentPath = $this->buildParentPath($dto->parentPartnerId);

        return Partner::create([
            'tenant_id'           => $tenantId,
            'parent_partner_id'   => $dto->parentPartnerId,
            'parent_path'         => $parentPath,
            'code'                => $dto->code,
            'name'                => $dto->name,
            'partner_type'        => $dto->partnerType,
            'ownership_type'      => $dto->ownershipType,
            'commission_enabled'  => $dto->commissionEnabled,
            'phone'               => $dto->phone,
            'email'               => $dto->email,
            'address'             => $dto->address,
            'status'              => $dto->status,
        ]);
    }

    public function updatePartner(string $partnerId, UpdatePartnerDTO $dto): Partner
    {
        $partner = $this->getPartnerById($partnerId);
        $tenantId = TenantContext::getInstance()->getTenantId();

        if ($partner->code !== $dto->code) {
            $this->assertCodeAvailable($dto->code, $tenantId, $partnerId);
        }

        if ($dto->parentPartnerId) {
            if ($dto->parentPartnerId === $partnerId) {
                throw new Exception('Partner cannot be its own parent.');
            }
            $this->assertParentBelongsToTenant($dto->parentPartnerId, $tenantId);
        }

        $parentPath = $this->buildParentPath($dto->parentPartnerId);

        $partner->update([
            'parent_partner_id'   => $dto->parentPartnerId,
            'parent_path'         => $parentPath,
            'code'                => $dto->code,
            'name'                => $dto->name,
            'partner_type'        => $dto->partnerType,
            'ownership_type'      => $dto->ownershipType,
            'commission_enabled'  => $dto->commissionEnabled,
            'phone'               => $dto->phone,
            'email'               => $dto->email,
            'address'             => $dto->address,
            'status'              => $dto->status,
        ]);

        return $partner->fresh();
    }

    public function deletePartner(string $partnerId): void
    {
        $partner = $this->getPartnerById($partnerId);

        if ($partner->children()->exists()) {
            throw new Exception('This partner has child partners and cannot be deleted.');
        }

        if ($partner->agreements()->exists()) {
            throw new Exception('This partner has agreements and cannot be deleted.');
        }

        $partner->delete();
    }

    private function assertCodeAvailable(string $code, ?string $tenantId, ?string $exceptPartnerId = null): void
    {
        $query = Partner::query()->where('code', $code);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereNull('tenant_id');
        }

        if ($exceptPartnerId) {
            $query->where('partner_id', '!=', $exceptPartnerId);
        }

        if ($query->exists()) {
            throw new Exception('Partner code is already registered in this context.');
        }
    }

    private function assertParentBelongsToTenant(string $parentPartnerId, ?string $tenantId): void
    {
        $query = Partner::query()->where('partner_id', $parentPartnerId);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if (!$query->exists()) {
            throw new Exception('Parent partner is invalid or not accessible in this tenant context.');
        }
    }

    private function buildParentPath(?string $parentPartnerId): ?string
    {
        if (!$parentPartnerId) {
            return null;
        }

        $parent = Partner::query()->where('partner_id', $parentPartnerId)->first();

        if (!$parent) {
            return $parentPartnerId;
        }

        if ($parent->parent_path) {
            return rtrim($parent->parent_path, '/') . '/' . $parent->partner_id;
        }

        return $parent->partner_id;
    }
}
