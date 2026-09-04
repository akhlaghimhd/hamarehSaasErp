<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Item;

/**
 * Lookup service for Item – Single Source of Truth in Inventory.
 * Other modules must use this Service (not the Item model directly).
 */
class ItemLookupService
{
    public function findById(string $itemId): ?object
    {
        return Item::query()
            ->where('item_id', $itemId)
            ->where('status', 1)
            ->first();
    }

    public function findByCode(string $code): ?object
    {
        return Item::query()
            ->where('code', $code)
            ->where('status', 1)
            ->first();
    }

    public function exists(string $itemId): bool
    {
        return Item::query()
            ->where('item_id', $itemId)
            ->where('status', 1)
            ->exists();
    }

    public function getBasicInfo(string $itemId): ?array
    {
        $item = $this->findById($itemId);

        if (!$item) {
            return null;
        }

        return [
            'item_id'           => $item->item_id,
            'code'              => $item->code,
            'name'              => $item->name,
            'item_type'         => $item->item_type,
            'uom_id'            => $item->uom_id,
            'valuation_method'  => $item->valuation_method,
            'status'            => $item->status,
        ];
    }
}
