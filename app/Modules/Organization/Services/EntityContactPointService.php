<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\EntityContactPoint;
use App\Modules\Organization\DTOs\CreateEntityContactPointDTO;
use App\Base\Context\TenantContext;

class EntityContactPointService
{
    public function create(CreateEntityContactPointDTO $dto): EntityContactPoint
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        if ($dto->isPrimary) {
            EntityContactPoint::where('tenant_id', $tenantId)
                ->where('entity_type', $dto->entityType)
                ->where('entity_id', $dto->entityId)
                ->where('contact_point_type', $dto->contactPointType)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        return EntityContactPoint::create([
            'tenant_id'           => $tenantId,
            'entity_type'         => $dto->entityType,
            'entity_id'           => $dto->entityId,
            'contact_point_type'  => $dto->contactPointType,
            'contact_value'       => $dto->contactValue,
            'is_primary'          => $dto->isPrimary,
            'status'              => $dto->status,
            'row_version'         => 1,
        ]);
    }

    public function listForEntity(string $entityType, string $entityId)
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        return EntityContactPoint::where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('is_primary')
            ->orderBy('contact_point_type')
            ->get();
    }

    public function softDelete(string $entityContactPointId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $point = EntityContactPoint::where('tenant_id', $tenantId)
            ->where('entity_contact_point_id', $entityContactPointId)
            ->firstOrFail();

        $point->delete();
    }
}
