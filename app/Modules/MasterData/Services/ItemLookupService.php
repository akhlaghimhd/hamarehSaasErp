<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Contracts\ItemLookupContract;
use App\Modules\MasterData\Models\Item;

class ItemLookupService implements ItemLookupContract
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
