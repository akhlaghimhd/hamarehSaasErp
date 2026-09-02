<?php

namespace App\Modules\MasterData\Contracts;

/**
 * Service Contract for Warehouse lookup across modules.
 * Owned by MasterData (Single Source of Truth).
 */
interface WarehouseLookupContract
{
    public function findById(string $warehouseId): ?object;

    public function exists(string $warehouseId): bool;

    public function getBasicInfo(string $warehouseId): ?array;
}
