<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Warehouse;

/**
 * Lookup service for Warehouse – Single Source of Truth in Inventory.
 * Other modules must use this Service (not the Warehouse model directly).
 */
class WarehouseLookupService
{
    public function findById(string $warehouseId): ?object
    {
        return Warehouse::query()
            ->where('warehouse_id', $warehouseId)
            ->where('status', 1)
            ->first();
    }

    public function exists(string $warehouseId): bool
    {
        return Warehouse::query()
            ->where('warehouse_id', $warehouseId)
            ->where('status', 1)
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
            'branch_id'    => $warehouse->branch_id,
            'is_bonded'    => $warehouse->is_bonded,
            'status'       => $warehouse->status,
        ];
    }
}
