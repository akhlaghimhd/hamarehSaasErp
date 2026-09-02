<?php

namespace App\Contracts\MasterData;

/**
 * Service Contract for Item lookup across modules.
 *
 * Used by ProcurementSales, Manufacturing, Accounting, etc.
 * Implementation lives in MasterData module (Single Source of Truth).
 * No Physical FK between modules – only UUID logical reference.
 */
interface ItemLookupContract
{
    /**
     * Find an active item by its primary key (item_id).
     * Returns null if not found or not belonging to current tenant.
     */
    public function findById(string $itemId): ?object;

    /**
     * Find an active item by its business code (SKU).
     */
    public function findByCode(string $code): ?object;

    /**
     * Check whether the given item_id exists and is active for the current tenant.
     */
    public function exists(string $itemId): bool;

    /**
     * Return basic read-only data needed by other modules
     * (id, code, name, item_type, base_uom, status).
     */
    public function getBasicInfo(string $itemId): ?array;
}
