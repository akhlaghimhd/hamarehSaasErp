<?php

namespace App\Contracts\MasterData;

/**
 * Service Contract for Warehouse lookup across modules.
 *
 * Used by Inventory (future), Manufacturing, ProcurementSales.
 * Implementation lives in MasterData module.
 */
interface WarehouseLookupContract
{
    /**
     * Find a warehouse by its primary key.
     */
    public function findById(string $warehouseId): ?object;

    /**
     * Check existence for current tenant.
     */
    public function exists(string $warehouseId): bool;

    /**
     * Return basic info (id, code, name, status).
     */
    public function getBasicInfo(string $warehouseId): ?array;
}
