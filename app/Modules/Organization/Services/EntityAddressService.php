<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\EntityAddress;
use App\Modules\Organization\DTOs\CreateEntityAddressDTO;
use App\Base\Context\TenantContext;

class EntityAddressService
{
    public function create(CreateEntityAddressDTO $dto): EntityAddress
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        if ($dto->isPrimary) {
            EntityAddress::where('tenant_id', $tenantId)
                ->where('entity_type', $dto->entityType)
                ->where('entity_id', $dto->entityId)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        return EntityAddress::create([
            'tenant_id'       => $tenantId,
            'entity_type'     => $dto->entityType,
            'entity_id'       => $dto->entityId,
            'address_type_id' => $dto->addressTypeId,
            'country_id'      => $dto->countryId,
            'province_name'   => $dto->provinceName,
            'city_name'       => $dto->cityName,
            'postal_code'    => $dto->postalCode,
            'address_text'    => $dto->addressText,
            'is_primary'      => $dto->isPrimary,
            'status'          => $dto->status,
            'row_version'     => 1,
        ]);
    }

    public function listForEntity(string $entityType, string $entityId)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        return EntityAddress::where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();
    }

    public function softDelete(string $entityAddressId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $address = EntityAddress::where('tenant_id', $tenantId)
            ->where('entity_address_id', $entityAddressId)
            ->firstOrFail();

        $address->delete();
    }
}
