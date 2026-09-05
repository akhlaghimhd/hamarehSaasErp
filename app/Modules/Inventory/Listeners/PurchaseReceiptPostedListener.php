<?php

namespace App\Modules\Inventory\Listeners;

use App\Modules\Inventory\Services\PurchaseReceiptGoodsReceiptService;
use App\Modules\ProcurementSales\Events\PurchaseReceiptPostedV1;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * L6-PS-04 – Consumes procurement.purchase-receipt.posted.v1 from ProcessOutboxMessageJob.
 * Creates Inventory Goods Receipt (draft) linked via source_document_* (no cross-module FK).
 */
class PurchaseReceiptPostedListener
{
    public function __construct(
        private readonly PurchaseReceiptGoodsReceiptService $goodsReceiptService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        try {
            $document = $this->goodsReceiptService->createFromPostedReceipt($payload);

            Log::info('Inventory Goods Receipt created from PurchaseReceiptPostedV1', [
                'document_id'         => $document->document_id,
                'purchase_receipt_id' => $payload['purchase_receipt_id'] ?? null,
                'lines'               => $document->items->count(),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to create Goods Receipt from PurchaseReceiptPostedV1: ' . $e->getMessage(), [
                'purchase_receipt_id' => $payload['purchase_receipt_id'] ?? null,
            ]);
            throw $e;
        }
    }
}
