<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\Item;

/**
 * Lookup service for Item – Single Source of Truth in MasterData.
 * Other modules must use this Service (not the Item model directly).
 * Per APP folder standard: inter-module access via destination module Services.
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
            'item_id'   => $item->item_id,
            'code'      => $item->code,
            'name'      => $item->name,
            'item_type' => $item->item_type,
            'base_uom'  => $item->base_uom,
            'status'    => $item->status,
        ];
    }
}
