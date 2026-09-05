<?php

namespace App\Modules\ProcurementSales\Services;

use App\Modules\ProcurementSales\DTOs\CreatePurchaseOrderDTO;
use App\Modules\ProcurementSales\Models\PurchaseOrder;
use App\Modules\ProcurementSales\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class PurchaseOrderService
{
    public const STATUS_DRAFT = 1;
    public const STATUS_SENT = 2;
    public const STATUS_PARTIALLY_RECEIVED = 3;
    public const STATUS_COMPLETED = 4;
    public const STATUS_CANCELLED = 0;

    public function createPurchaseOrder(CreatePurchaseOrderDTO $dto): PurchaseOrder
    {
        try {
            return DB::transaction(function () use ($dto) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');

                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $subtotal = 0.0;
                $taxTotal = 0.0;
                $discountTotal = 0.0;

                foreach ($dto->items as $item) {
                    $lineNet = ($item->quantity * $item->unitPrice) - $item->discountAmount;
                    $subtotal += $lineNet;
                    $taxTotal += $item->taxAmount;
                    $discountTotal += $item->discountAmount;
                }

                $totalAmount = $subtotal + $taxTotal;
                $orderNumber = 'PO-' . strtoupper(Str::random(8));

                $purchaseOrder = PurchaseOrder::create([
                    'tenant_id'       => $tenantId,
                    'order_number'    => $orderNumber,
                    'supplier_id'     => $dto->supplierId,
                    'order_date'      => $dto->orderDate,
                    'delivery_date'   => $dto->deliveryDate,
                    'subtotal_amount' => $subtotal,
                    'tax_amount'      => $taxTotal,
                    'discount_amount' => $discountTotal,
                    'total_amount'    => $totalAmount,
                    'status'          => self::STATUS_DRAFT,
                    'currency_id'     => $dto->currencyId,
                    'created_by'      => $userId,
                    'updated_by'      => $userId,
                    'row_version'     => 1,
                ]);

                $lineNumber = 1;
                foreach ($dto->items as $itemDto) {
                    $lineNet = ($itemDto->quantity * $itemDto->unitPrice) - $itemDto->discountAmount;

                    PurchaseOrderItem::create([
                        'tenant_id'         => $tenantId,
                        'purchase_order_id' => $purchaseOrder->purchase_order_id,
                        'item_id'           => $itemDto->itemId,
                        'quantity'          => $itemDto->quantity,
                        'unit_price'        => $itemDto->unitPrice,
                        'discount_amount'   => $itemDto->discountAmount,
                        'tax_amount'        => $itemDto->taxAmount,
                        'total_price'       => $lineNet + $itemDto->taxAmount,
                        'uom_code'          => $itemDto->uomCode,
                        'line_number'       => $itemDto->lineNumber > 0 ? $itemDto->lineNumber : $lineNumber,
                        'description'       => $itemDto->description,
                        'created_by'        => $userId,
                        'updated_by'        => $userId,
                        'row_version'       => 1,
                    ]);
                    $lineNumber++;
                }

                return $purchaseOrder->load('items');
            });
        } catch (Exception $e) {
            Log::error('Failed to create PurchaseOrder: ' . $e->getMessage());
            throw $e;
        }
    }
}
