<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Validation\ValidationException;

/**
 * Lookup service for Warehouse – Single Source of Truth in Inventory (L6-INV-10).
 * Other modules and Inventory domain services must use this Service
 * (not the Warehouse model directly) for existence / basic info checks.
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

    /**
     * Assert active warehouse exists in current tenant scope; throw 422 otherwise.
     */
    public function requireActive(string $warehouseId): object
    {
        $warehouse = $this->findById($warehouseId);

        if (!$warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['The selected warehouse is invalid or inactive for this tenant.'],
            ]);
        }

        return $warehouse;
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
