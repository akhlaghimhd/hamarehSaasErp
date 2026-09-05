<?php

namespace App\Modules\Inventory\Events;

use App\Modules\Inventory\Models\InventoryDocument;

/**
 * Integration event — emitted when an inventory document is posted and stock moved.
 * Event type string: inventory.stock_movement.posted.v1
 */
final class StockMovementPostedV1
{
    public const EVENT_TYPE = 'inventory.stock_movement.posted.v1';
    public const AGGREGATE_TYPE = 'inv_documents';

    /**
     * @return array<string, mixed>
     */
    public static function payload(InventoryDocument $document): array
    {
        $lines = [];
        foreach ($document->items as $line) {
            $lines[] = [
                'document_item_id' => $line->document_item_id,
                'item_id'          => $line->item_id,
                'quantity'         => (float) $line->quantity,
                'unit_cost'        => (float) $line->unit_cost,
                'from_location_id' => $line->from_location_id,
                'to_location_id'   => $line->to_location_id,
                'batch_number'     => $line->batch_number,
            ];
        }

        return [
            'event'                 => self::EVENT_TYPE,
            'tenant_id'             => $document->tenant_id,
            'document_id'           => $document->document_id,
            'document_number'       => $document->document_number,
            'document_type'         => (int) $document->document_type,
            'status'                => (int) $document->status,
            'posting_date'          => optional($document->posting_date)?->toIso8601String(),
            'accounting_voucher_id' => $document->accounting_voucher_id,
            'lines'                 => $lines,
        ];
    }
}
