<?php

namespace App\Modules\Inventory\Events;

use App\Modules\Inventory\Models\StockBalance;

/** Event type: inventory.stock.reserved.v1 */
final class StockReservedV1
{
    public const EVENT_TYPE = 'inventory.stock.reserved.v1';
    public const AGGREGATE_TYPE = 'inv_stock_balances';

    /**
     * @param  array{source_document_type?: string|null, source_document_id?: string|null}  $meta
     * @return array<string, mixed>
     */
    public static function payload(StockBalance $balance, float $quantity, array $meta = []): array
    {
        return [
            'event'                => self::EVENT_TYPE,
            'tenant_id'            => $balance->tenant_id,
            'stock_balance_id'     => $balance->stock_balance_id,
            'warehouse_id'         => $balance->warehouse_id,
            'location_id'          => $balance->location_id,
            'item_id'              => $balance->item_id,
            'quantity'             => $quantity,
            'quantity_on_hand'     => (float) $balance->quantity_on_hand,
            'quantity_reserved'    => (float) $balance->quantity_reserved,
            'source_document_type' => $meta['source_document_type'] ?? null,
            'source_document_id'   => $meta['source_document_id'] ?? null,
        ];
    }
}
