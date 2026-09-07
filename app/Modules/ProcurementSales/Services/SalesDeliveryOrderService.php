<?php

namespace App\Modules\ProcurementSales\Services;

use App\Modules\ProcurementSales\DTOs\CreateSalesDeliveryOrderDTO;
use App\Modules\ProcurementSales\Events\SalesDeliveryPostedV1;
use App\Modules\ProcurementSales\Models\SalesDeliveryOrder;
use App\Modules\ProcurementSales\Models\SalesDeliveryOrderItem;
use App\Modules\ProcurementSales\Support\OutboxPublisher;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * L6-PS-05 – Sales Delivery create (Prepared) and post (Dispatched → Inventory Issue).
 */
class SalesDeliveryOrderService
{
    public const STATUS_PREPARED = 1;
    public const STATUS_DISPATCHED = 2;
    public const STATUS_DELIVERED = 3;
    public const STATUS_CANCELLED = 0;

    public function createDeliveryOrder(CreateSalesDeliveryOrderDTO $dto): SalesDeliveryOrder
    {
        try {
            return DB::transaction(function () use ($dto) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');

                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                if (empty($dto->items)) {
                    throw new ConflictHttpException('Delivery order must have at least one line.');
                }

                if (empty($dto->warehouseId)) {
                    throw new ConflictHttpException('warehouse_id is required for sales delivery.');
                }

                $deliveryNumber = 'SDO-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

                $delivery = SalesDeliveryOrder::create([
                    'tenant_id'             => $tenantId,
                    'delivery_number'       => $deliveryNumber,
                    'id_sales_order_source' => $dto->salesOrderId,
                    'customer_id'           => $dto->customerId,
                    'warehouse_id'          => $dto->warehouseId,
                    'shipping_date'         => $dto->shippingDate,
                    'shipping_address'      => $dto->shippingAddress,
                    'status'                => self::STATUS_PREPARED,
                    'created_by'            => $userId,
                    'updated_by'            => $userId,
                    'row_version'           => 1,
                ]);

                $lineNumber = 1;
                foreach ($dto->items as $item) {
                    if ($item->deliveredQuantity <= 0) {
                        throw new ConflictHttpException('delivered_quantity must be greater than zero.');
                    }

                    SalesDeliveryOrderItem::create([
                        'tenant_id'            => $tenantId,
                        'delivery_order_id'    => $delivery->delivery_order_id,
                        'sales_order_item_id'  => $item->salesOrderItemId,
                        'item_id'              => $item->itemId,
                        'ordered_quantity'     => $item->orderedQuantity,
                        'delivered_quantity'   => $item->deliveredQuantity,
                        'unit_price'           => $item->unitPrice,
                        'total_price'          => $item->deliveredQuantity * $item->unitPrice,
                        'uom_code'             => $item->uomCode,
                        'line_number'          => $item->lineNumber ?: $lineNumber,
                        'notes'                => $item->notes,
                        'created_by'           => $userId,
                        'updated_by'           => $userId,
                        'row_version'          => 1,
                    ]);
                    $lineNumber++;
                }

                return $delivery->load('items');
            });
        } catch (Exception $e) {
            Log::error('Failed to create SalesDeliveryOrder: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Post Prepared delivery → STATUS_DISPATCHED + SalesDeliveryPostedV1 for Inventory Issue.
     */
    public function post(string $id): SalesDeliveryOrder
    {
        try {
            return DB::transaction(function () use ($id) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');

                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $delivery = SalesDeliveryOrder::with('items')->find($id);
                if (!$delivery) {
                    throw new NotFoundHttpException('Sales delivery order not found.');
                }

                if ((int) $delivery->status !== self::STATUS_PREPARED) {
                    throw new ConflictHttpException('Only prepared delivery orders can be posted.');
                }

                if ($delivery->items->isEmpty()) {
                    throw new ConflictHttpException('Cannot post a delivery with no lines.');
                }

                if (empty($delivery->warehouse_id)) {
                    throw new ConflictHttpException(
                        'warehouse_id is required before posting (logical ref to Inventory warehouse).'
                    );
                }

                $delivery->update([
                    'status'      => self::STATUS_DISPATCHED,
                    'updated_by'  => $userId,
                    'row_version' => ((int) ($delivery->row_version ?? 1)) + 1,
                ]);

                $lines = [];
                foreach ($delivery->items as $item) {
                    $lines[] = [
                        'item_id'     => $item->item_id,
                        'quantity'    => (string) $item->delivered_quantity,
                        'unit_price'  => (string) $item->unit_price,
                        'line_number' => (int) $item->line_number,
                    ];
                }

                $event = new SalesDeliveryPostedV1(
                    tenantId: $tenantId,
                    deliveryOrderId: $delivery->delivery_order_id,
                    deliveryNumber: $delivery->delivery_number,
                    customerId: $delivery->customer_id,
                    salesOrderId: $delivery->id_sales_order_source,
                    shippingDate: $delivery->shipping_date?->toIso8601String()
                        ?? (string) $delivery->shipping_date,
                    lines: $lines,
                );

                $payload = $event->toPayload();
                $payload['warehouse_id'] = $delivery->warehouse_id;
                $payload['posted_by'] = $userId;

                OutboxPublisher::publish(
                    $tenantId,
                    'sales_delivery_orders',
                    $delivery->delivery_order_id,
                    SalesDeliveryPostedV1::EVENT_TYPE,
                    $payload,
                );

                return $delivery->fresh(['items']);
            });
        } catch (Exception $e) {
            Log::error('Failed to post SalesDeliveryOrder: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getById(string $id): SalesDeliveryOrder
    {
        $delivery = SalesDeliveryOrder::with('items')->find($id);
        if (!$delivery) {
            throw new NotFoundHttpException('Sales delivery order not found.');
        }

        return $delivery;
    }
}
