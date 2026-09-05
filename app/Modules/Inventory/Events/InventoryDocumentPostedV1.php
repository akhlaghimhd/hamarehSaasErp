<?php

namespace App\Modules\Inventory\Events;

use App\Modules\Inventory\Models\InventoryDocument;

/**
 * Domain event — document lifecycle posted.
 * Event type string: inventory.document.posted.v1
 */
final class InventoryDocumentPostedV1
{
    public const EVENT_TYPE = 'inventory.document.posted.v1';
    public const AGGREGATE_TYPE = 'inv_documents';

    /**
     * @return array<string, mixed>
     */
    public static function payload(InventoryDocument $document): array
    {
        return [
            'event'                 => self::EVENT_TYPE,
            'tenant_id'             => $document->tenant_id,
            'document_id'           => $document->document_id,
            'document_number'       => $document->document_number,
            'document_type'         => (int) $document->document_type,
            'status'                => (int) $document->status,
            'accounting_voucher_id' => $document->accounting_voucher_id,
        ];
    }
}
