<?php

namespace App\Modules\ProcurementSales\Services;

use App\Modules\ProcurementSales\DTOs\CreatePurchaseReceiptDTO;
use App\Modules\ProcurementSales\Events\PurchaseReceiptPostedV1;
use App\Modules\ProcurementSales\Models\PurchaseReceipt;
use App\Modules\ProcurementSales\Models\PurchaseReceiptItem;
use App\Modules\ProcurementSales\Support\OutboxPublisher;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * L6-PS-03/04 – Purchase Receipt create + post.
 * On Post: emit PurchaseReceiptPostedV1 to event_outbox for Inventory Goods Receipt.
 */
class PurchaseReceiptService
{
    public const STATUS_DRAFT = 1;
    public const STATUS_VERIFIED = 2;
    public const STATUS_POSTED = 3;
    public const STATUS_CANCELLED = 0;

    public function createReceipt(CreatePurchaseReceiptDTO $dto): PurchaseReceipt
    {
        try {
            return DB::transaction(function () use ($dto) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');

                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                if (count($dto->items) < 1) {
                    throw new Exception('Purchase receipt must have at least one line.');
                }

                $receiptNumber = 'PR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));

                $receipt = PurchaseReceipt::create([
                    'tenant_id'                 => $tenantId,
                    'receipt_number'            => $receiptNumber,
                    'id_purchase_order_source'  => $dto->purchaseOrderId,
                    'supplier_id'               => $dto->supplierId,
                    'warehouse_id'              => $dto->warehouseId,
                    'receipt_date'              => $dto->receiptDate,
                    'status'                    => self::STATUS_DRAFT,
                    'notes'                     => $dto->notes,
                    'created_by'                => $userId,
                    'updated_by'                => $userId,
                    'row_version'               => 1,
                ]);

                $lineNumber = 1;
                foreach ($dto->items as $itemDto) {
                    $lineTotal = $itemDto->receivedQuantity * $itemDto->unitPrice;

                    PurchaseReceiptItem::create([
                        'tenant_id'              => $tenantId,
                        'purchase_receipt_id'    => $receipt->purchase_receipt_id,
                        'purchase_order_item_id' => $itemDto->purchaseOrderItemId,
                        'item_id'                => $itemDto->itemId,
                        'ordered_quantity'       => $itemDto->orderedQuantity,
                        'received_quantity'      => $itemDto->receivedQuantity,
                        'unit_price'             => $itemDto->unitPrice,
                        'total_price'            => $lineTotal,
                        'uom_code'               => $itemDto->uomCode,
                        'line_number'            => $itemDto->lineNumber > 0 ? $itemDto->lineNumber : $lineNumber,
                        'notes'                  => $itemDto->notes,
                        'created_by'             => $userId,
                        'updated_by'             => $userId,
                        'row_version'            => 1,
                    ]);
                    $lineNumber++;
                }

                return $receipt->load('items');
            });
        } catch (Exception $e) {
            Log::error('Failed to create PurchaseReceipt: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Post a draft receipt: status → Posted and publish boundary event for Inventory.
     */
    public function postReceipt(string $purchaseReceiptId): PurchaseReceipt
    {
        try {
            return DB::transaction(function () use ($purchaseReceiptId) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');

                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $receipt = PurchaseReceipt::with('items')->find($purchaseReceiptId);
                if (!$receipt) {
                    throw new NotFoundHttpException('Purchase receipt not found.');
                }

                if ((int) $receipt->status === self::STATUS_POSTED) {
                    throw new ConflictHttpException('Purchase receipt is already posted.');
                }

                if ((int) $receipt->status === self::STATUS_CANCELLED) {
                    throw new ConflictHttpException('Cancelled purchase receipt cannot be posted.');
                }

                if ((int) $receipt->status !== self::STATUS_DRAFT && (int) $receipt->status !== self::STATUS_VERIFIED) {
                    throw new ConflictHttpException('Only draft or verified purchase receipts can be posted.');
                }

                if ($receipt->items->isEmpty()) {
                    throw new ConflictHttpException('Cannot post a purchase receipt with no lines.');
                }

                if (empty($receipt->warehouse_id)) {
                    throw new ConflictHttpException('warehouse_id is required before posting (logical ref to Inventory warehouse).');
                }

                $receipt->update([
                    'status'      => self::STATUS_POSTED,
                    'updated_by'  => $userId,
                    'row_version' => ((int) ($receipt->row_version ?? 1)) + 1,
                ]);

                $lines = [];
                foreach ($receipt->items as $item) {
                    $lines[] = [
                        'item_id'     => $item->item_id,
                        'quantity'    => (string) $item->received_quantity,
                        'unit_price'  => (string) $item->unit_price,
                        'line_number' => (int) $item->line_number,
                    ];
                }

                $event = new PurchaseReceiptPostedV1(
                    tenantId: $tenantId,
                    purchaseReceiptId: $receipt->purchase_receipt_id,
                    receiptNumber: $receipt->receipt_number,
                    supplierId: $receipt->supplier_id,
                    purchaseOrderId: $receipt->id_purchase_order_source,
                    receiptDate: $receipt->receipt_date?->toIso8601String() ?? (string) $receipt->receipt_date,
                    lines: $lines,
                );

                $payload = $event->toPayload();
                $payload['warehouse_id'] = $receipt->warehouse_id;

                OutboxPublisher::publish(
                    $tenantId,
                    'purchase_receipts',
                    $receipt->purchase_receipt_id,
                    PurchaseReceiptPostedV1::EVENT_TYPE,
                    $payload,
                );

                return $receipt->fresh(['items']);
            });
        } catch (Exception $e) {
            Log::error('Failed to post PurchaseReceipt: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getById(string $id): PurchaseReceipt
    {
        $receipt = PurchaseReceipt::with('items')->find($id);
        if (!$receipt) {
            throw new NotFoundHttpException('Purchase receipt not found.');
        }

        return $receipt;
    }
}
