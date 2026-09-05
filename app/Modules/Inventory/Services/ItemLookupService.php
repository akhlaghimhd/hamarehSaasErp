<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Item;
use Illuminate\Validation\ValidationException;

/**
 * Lookup service for Item – Single Source of Truth in Inventory (L6-INV-10).
 * Other modules and Inventory domain services must use this Service
 * (not the Item model directly) for existence / basic info checks.
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

    /**
     * Assert active item exists in current tenant scope; throw 422 otherwise.
     */
    public function requireActive(string $itemId): object
    {
        $item = $this->findById($itemId);

        if (!$item) {
            throw ValidationException::withMessages([
                'item_id' => ['The selected item is invalid or inactive for this tenant.'],
            ]);
        }

        return $item;
    }

    public function getBasicInfo(string $itemId): ?array
    {
        $item = $this->findById($itemId);

        if (!$item) {
            return null;
        }

        return [
            'item_id'          => $item->item_id,
            'code'             => $item->code,
            'name'             => $item->name,
            'item_type'        => $item->item_type,
            'uom_id'           => $item->uom_id,
            'valuation_method' => $item->valuation_method,
            'status'           => $item->status,
        ];
    }
}
