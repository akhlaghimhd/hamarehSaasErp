<?php

namespace App\Modules\MasterData\Services;

use App\Contracts\MasterData\WarehouseLookupContract;
use App\Modules\MasterData\Models\Warehouse;

/**
 * Concrete implementation of WarehouseLookupContract.
 * Owned by MasterData module (Single Source of Truth).
 */
class WarehouseLookupService implements WarehouseLookupContract
{
    public function findById(string $warehouseId): ?object
    {
        return Warehouse::query()
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->first();
    }

    public function exists(string $warehouseId): bool
    {
        return Warehouse::query()
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->exists();
    }

    public function getBasicInfo(string $warehouseId): ?array
    {
        $warehouse = $this->findById($warehouseId);

        if (!$warehouse) {
            return null;
        }

        return [
            'warehouse_id' => $warehouse->warehouse_id,
            'code'         => $warehouse->code,
            'name'         => $warehouse->name,
            'location'     => $warehouse->location,
            'is_active'    => $warehouse->is_active,
        ];
    }
}
