<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\DTOs\CreateInventoryDocumentDTO;
use App\Modules\Inventory\DTOs\CreateInventoryDocumentItemDTO;
use App\Modules\Inventory\Models\InventoryDocument;
use App\Modules\Inventory\Models\Location;
use App\Modules\ProcurementSales\Events\PurchaseReceiptPostedV1;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * L6-PS-04 – Inventory side: create Goods Receipt document from PurchaseReceiptPostedV1.
 * No physical FK to Procurement; linkage via source_document_type / source_document_id.
 */
class PurchaseReceiptGoodsReceiptService
{
    public const SOURCE_TYPE = 'PUR_RECEIPT';

    public function __construct(
        private readonly InventoryDocumentService $documentService,
        private readonly InventoryDocumentItemService $itemService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload  Outbox payload from PurchaseReceiptPostedV1 (+ warehouse_id)
     */
    public function createFromPostedReceipt(array $payload): InventoryDocument
    {
        $tenantId = Context::get('tenant_id') ?: ($payload['tenant_id'] ?? null);
        if (!$tenantId) {
            throw new Exception('Tenant Context is missing for PurchaseReceipt → Goods Receipt.');
        }
        Context::add('tenant_id', $tenantId);

        $purchaseReceiptId = $payload['purchase_receipt_id'] ?? null;
        $warehouseId = $payload['warehouse_id'] ?? null;
        $lines = $payload['lines'] ?? [];

        if (empty($purchaseReceiptId)) {
            throw new Exception('purchase_receipt_id is required in PurchaseReceiptPosted payload.');
        }
        if (empty($warehouseId)) {
            throw new Exception('warehouse_id is required in PurchaseReceiptPosted payload.');
        }
        if (!is_array($lines) || count($lines) < 1) {
            throw new Exception('PurchaseReceiptPosted payload must contain at least one line.');
        }

        // Idempotency: one GR document per posted purchase receipt
        $existing = InventoryDocument::query()
            ->where('source_document_type', self::SOURCE_TYPE)
            ->where('source_document_id', $purchaseReceiptId)
            ->first();

        if ($existing) {
            Log::info('Goods Receipt already exists for purchase receipt; skipping.', [
                'purchase_receipt_id' => $purchaseReceiptId,
                'document_id'         => $existing->document_id,
            ]);

            return $existing->load('items');
        }

        $toLocationId = $this->resolveDefaultLocationId($warehouseId);

        return DB::transaction(function () use ($payload, $purchaseReceiptId, $warehouseId, $lines, $toLocationId) {
            $receiptNumber = $payload['receipt_number'] ?? Str::random(8);
            $documentNumber = 'GR-' . $receiptNumber;
            $postingDate = isset($payload['receipt_date'])
                ? substr((string) $payload['receipt_date'], 0, 10)
                : now()->toDateString();

            $dto = new CreateInventoryDocumentDTO(
                fiscal_period_id: (string) Str::uuid(), // logical; Accounting period wiring is later (L6-PS-06)
                document_type: InventoryDocumentService::TYPE_RECEIPT,
                document_number: $documentNumber,
                posting_date: $postingDate,
                source_document_type: self::SOURCE_TYPE,
                source_document_id: $purchaseReceiptId,
                business_partner_id: $payload['supplier_id'] ?? null,
                description: 'Auto Goods Receipt from Purchase Receipt ' . $receiptNumber
                    . ' (warehouse ' . $warehouseId . ')',
                status: InventoryDocumentService::STATUS_DRAFT,
            );

            $document = $this->documentService->createDocument($dto);

            $sort = 1;
            foreach ($lines as $line) {
                $itemId = $line['item_id'] ?? null;
                $qty = (float) ($line['quantity'] ?? 0);
                $unitCost = (float) ($line['unit_price'] ?? 0);

                if (empty($itemId) || $qty <= 0) {
                    throw new ConflictHttpException('Invalid line in PurchaseReceiptPosted payload.');
                }

                $itemDto = new CreateInventoryDocumentItemDTO(
                    document_id: $document->document_id,
                    item_id: $itemId,
                    quantity: $qty,
                    unit_cost: $unitCost,
                    from_location_id: null,
                    to_location_id: $toLocationId,
                    batch_number: null,
                    sort_order: (int) ($line['line_number'] ?? $sort),
                );

                $this->itemService->createItem($itemDto);
                $sort++;
            }

            return $document->fresh(['items']);
        });
    }

    private function resolveDefaultLocationId(string $warehouseId): string
    {
        $location = Location::query()
            ->where('warehouse_id', $warehouseId)
            ->where('status', 1)
            ->orderBy('code')
            ->first();

        if (!$location) {
            throw new ConflictHttpException(
                'No active location found for warehouse ' . $warehouseId
                . '. Create a location before posting purchase receipts.'
            );
        }

        return $location->location_id;
    }
}
