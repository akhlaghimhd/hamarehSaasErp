<?php

namespace App\Modules\Inventory\Listeners;

use App\Modules\Inventory\Services\SalesDeliveryStockIssueService;
use App\Modules\ProcurementSales\Events\SalesDeliveryPostedV1;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * L6-PS-05 – Consumes procurement.sales-delivery.posted.v1.
 * Releases reservation (if any) and posts Inventory Goods Issue.
 */
class SalesDeliveryPostedListener
{
    public function __construct(
        private readonly SalesDeliveryStockIssueService $issueService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        try {
            $document = $this->issueService->issueFromPostedDelivery($payload);

            Log::info('Inventory Goods Issue created and posted from SalesDeliveryPostedV1', [
                'document_id'       => $document->document_id,
                'status'            => $document->status,
                'delivery_order_id' => $payload['delivery_order_id'] ?? null,
                'lines'             => $document->items->count(),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to issue stock from SalesDeliveryPostedV1: ' . $e->getMessage(), [
                'delivery_order_id' => $payload['delivery_order_id'] ?? null,
            ]);
            throw $e;
        }
    }
}
