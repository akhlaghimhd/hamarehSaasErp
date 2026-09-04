<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\StockBalance;
use Illuminate\Database\Eloquent\Collection;

/**
 * L6-INV-09 — Read-only access to inv_stock_balances.
 * Balances are mutated only by InventoryDocumentService::postDocument (and future void/reserve).
 */
class StockBalanceService
{
    /**
     * @param  array{warehouse_id?: string, location_id?: string, item_id?: string}  $filters
     */
    public function getAllBalances(array $filters = []): Collection
    {
        $query = StockBalance::query();

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (!empty($filters['item_id'])) {
            $query->where('item_id', $filters['item_id']);
        }

        return $query
            ->orderBy('warehouse_id')
            ->orderBy('location_id')
            ->orderBy('item_id')
            ->get();
    }

    public function getBalanceById(string $id): StockBalance
    {
        return StockBalance::findOrFail($id);
    }
}
