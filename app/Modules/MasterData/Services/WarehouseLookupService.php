<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\Warehouse;

/**
 * Lookup service for Warehouse – Single Source of Truth in MasterData.
 * Other modules must use this Service (not the Warehouse model directly).
 */
class WarehouseLookupService
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
