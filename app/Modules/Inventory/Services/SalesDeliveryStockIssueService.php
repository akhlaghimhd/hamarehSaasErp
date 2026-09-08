<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Accounting\Services\FiscalPeriodService;
use App\Modules\Inventory\DTOs\CreateInventoryDocumentDTO;
use App\Modules\Inventory\DTOs\CreateInventoryDocumentItemDTO;
use App\Modules\Inventory\Models\InventoryDocument;
use App\Modules\Inventory\Models\Location;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * L6-PS-05 – Inventory side: Issue stock from SalesDeliveryPostedV1.
 * Releases soft reservation (if any) then creates+posts TYPE_ISSUE document.
 * No physical FK; linkage via source_document_type / source_document_id.
 *
 * L6-PS-06: fiscal_period_id resolved from open Accounting fiscal period.
 */
class SalesDeliveryStockIssueService
{
    public const SOURCE_TYPE = 'SAL_DELIVERY';

    public function __construct(
        private readonly InventoryDocumentService $documentService,
        private readonly InventoryDocumentItemService $itemService,
        private readonly StockReservationService $reservationService,
        private readonly FiscalPeriodService $fiscalPeriodService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function issueFromPostedDelivery(array $payload): InventoryDocument
    {
        $tenantId = Context::get('tenant_id') ?: ($payload['tenant_id'] ?? null);
        if (!$tenantId) {
            throw new Exception('Tenant Context is missing for SalesDelivery → Stock Issue.');
        }
        Context::add('tenant_id', $tenantId);

        if (!Context::get('user_id') && !empty($payload['posted_by'])) {
            Context::add('user_id', $payload['posted_by']);
        }

        $deliveryOrderId = $payload['delivery_order_id'] ?? null;
        $warehouseId = $payload['warehouse_id'] ?? null;
        $lines = $payload['lines'] ?? [];
        $salesOrderId = $payload['sales_order_id'] ?? null;

        if (empty($deliveryOrderId)) {
            throw new Exception('delivery_order_id is required in SalesDeliveryPosted payload.');
        }
        if (empty($warehouseId)) {
            throw new Exception('warehouse_id is required in SalesDeliveryPosted payload.');
        }
        if (!is_array($lines) || count($lines) < 1) {
            throw new Exception('SalesDeliveryPosted payload must contain at least one line.');
        }

        // Idempotency
        $existing = InventoryDocument::query()
            ->where('source_document_type', self::SOURCE_TYPE)
            ->where('source_document_id', $deliveryOrderId)
            ->first();

        if ($existing) {
            Log::info('Goods Issue already exists for sales delivery (idempotent)', [
                'document_id' => $existing->document_id,
                'status'      => $existing->status,
            ]);
            if ((int) $existing->status === InventoryDocumentService::STATUS_DRAFT) {
                return $this->documentService->postDocument($existing->document_id);
            }
            return $existing->load('items');
        }

        $fromLocationId = $this->resolveDefaultLocationId($warehouseId);

        $document = DB::transaction(function () use ($payload, $deliveryOrderId, $warehouseId, $lines, $fromLocationId, $salesOrderId) {
            // Best-effort release of soft reservations from SO confirm
            foreach ($lines as $line) {
                $itemId = $line['item_id'] ?? null;
                $qty = (float) ($line['quantity'] ?? 0);
                if (empty($itemId) || $qty <= 0 || empty($salesOrderId)) {
                    continue;
                }
                try {
                    $this->reservationService->release(
                        $fromLocationId,
                        $itemId,
                        $qty,
                        [
                            'source_document_type' => 'SAL_ORDER',
                            'source_document_id'   => $salesOrderId,
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::info('Reservation release skipped for delivery line', [
                        'item_id' => $itemId,
                        'reason'  => $e->getMessage(),
                    ]);
                }
            }

            $deliveryNumber = $payload['delivery_number'] ?? Str::random(8);
            $documentNumber = 'GI-' . $deliveryNumber;
            $postingDate = isset($payload['shipping_date'])
                ? substr((string) $payload['shipping_date'], 0, 10)
                : now()->toDateString();

            // L6-PS-06: resolve open fiscal period from Accounting
            $fiscalPeriodId = $this->fiscalPeriodService->resolveOpenPeriodIdForDate($postingDate)
                ?? (string) Str::uuid();

            $dto = new CreateInventoryDocumentDTO(
                fiscal_period_id: $fiscalPeriodId,
                document_type: InventoryDocumentService::TYPE_ISSUE,
                document_number: $documentNumber,
                posting_date: $postingDate,
                source_document_type: self::SOURCE_TYPE,
                source_document_id: $deliveryOrderId,
                business_partner_id: $payload['customer_id'] ?? null,
                description: 'Auto Goods Issue from Sales Delivery ' . $deliveryNumber
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
                    throw new ConflictHttpException('Invalid line in SalesDeliveryPosted payload.');
                }

                $itemDto = new CreateInventoryDocumentItemDTO(
                    document_id: $document->document_id,
                    item_id: $itemId,
                    quantity: $qty,
                    unit_cost: $unitCost,
                    from_location_id: $fromLocationId,
                    to_location_id: null,
                    batch_number: null,
                    sort_order: (int) ($line['line_number'] ?? $sort),
                );

                $this->itemService->createItem($itemDto);
                $sort++;
            }

            return $document->fresh(['items']);
        });

        return $this->documentService->postDocument($document->document_id);
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
                . '. Create a location before posting sales deliveries.'
            );
        }

        return $location->location_id;
    }
}
