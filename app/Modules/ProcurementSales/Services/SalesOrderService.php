<?php

namespace App\Modules\ProcurementSales\Services;

use App\Modules\ProcurementSales\DTOs\CreateSalesOrderDTO;
use App\Modules\ProcurementSales\Events\SalesOrderConfirmedV1;
use App\Modules\ProcurementSales\Models\SalesOrder;
use App\Modules\ProcurementSales\Models\SalesOrderItem;
use App\Modules\ProcurementSales\Support\OutboxPublisher;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * L6-PS-05 – Sales Order create (Draft) and confirm (→ Outbox for stock reservation).
 */
class SalesOrderService
{
    public const STATUS_DRAFT = 1;
    public const STATUS_CONFIRMED = 2;
    public const STATUS_PROCESSING = 3;
    public const STATUS_DELIVERED = 4;
    public const STATUS_INVOICED = 5;
    public const STATUS_CANCELLED = 0;

    public function createSalesOrder(CreateSalesOrderDTO $dto): SalesOrder
    {
        try {
            return DB::transaction(function () use ($dto) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');

                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                if (empty($dto->items)) {
                    throw new ConflictHttpException('Sales order must have at least one line.');
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

                $total = $subtotal + $taxTotal;
                $orderNumber = 'SO-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

                $salesOrder = SalesOrder::create([
                    'tenant_id'        => $tenantId,
                    'order_number'     => $orderNumber,
                    'customer_id'      => $dto->customerId,
                    'order_date'       => $dto->orderDate,
                    'delivery_date'    => $dto->deliveryDate,
                    'subtotal_amount'  => $subtotal,
                    'tax_amount'       => $taxTotal,
                    'discount_amount'  => $discountTotal,
                    'total_amount'     => $total,
                    'status'           => self::STATUS_DRAFT,
                    'currency_id'      => $dto->currencyId,
                    'warehouse_id'     => $dto->warehouseId,
                    'created_by'       => $userId,
                    'updated_by'       => $userId,
                    'row_version'      => 1,
                ]);

                $lineNumber = 1;
                foreach ($dto->items as $item) {
                    $lineNet = ($item->quantity * $item->unitPrice) - $item->discountAmount;
                    SalesOrderItem::create([
                        'tenant_id'        => $tenantId,
                        'sales_order_id'   => $salesOrder->sales_order_id,
                        'item_id'          => $item->itemId,
                        'quantity'         => $item->quantity,
                        'unit_price'       => $item->unitPrice,
                        'discount_amount'  => $item->discountAmount,
                        'tax_amount'       => $item->taxAmount,
                        'total_price'      => $lineNet + $item->taxAmount,
                        'uom_code'         => $item->uomCode,
                        'line_number'      => $item->lineNumber ?: $lineNumber,
                        'description'      => $item->description,
                        'created_by'       => $userId,
                        'updated_by'       => $userId,
                        'row_version'      => 1,
                    ]);
                    $lineNumber++;
                }

                return $salesOrder->load('items');
            });
        } catch (Exception $e) {
            Log::error('Failed to create SalesOrder: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Confirm Draft SO → STATUS_CONFIRMED + publish SalesOrderConfirmedV1 for Inventory reservation.
     */
    public function confirm(string $id): SalesOrder
    {
        try {
            return DB::transaction(function () use ($id) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');

                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $order = SalesOrder::with('items')->find($id);
                if (!$order) {
                    throw new NotFoundHttpException('Sales order not found.');
                }

                if ((int) $order->status !== self::STATUS_DRAFT) {
                    throw new ConflictHttpException('Only draft sales orders can be confirmed.');
                }

                if ($order->items->isEmpty()) {
                    throw new ConflictHttpException('Cannot confirm a sales order with no lines.');
                }

                if (empty($order->warehouse_id)) {
                    throw new ConflictHttpException(
                        'warehouse_id is required before confirm (logical ref to Inventory warehouse for reservation).'
                    );
                }

                $order->update([
                    'status'      => self::STATUS_CONFIRMED,
                    'updated_by'  => $userId,
                    'row_version' => ((int) ($order->row_version ?? 1)) + 1,
                ]);

                $lines = [];
                foreach ($order->items as $item) {
                    $lines[] = [
                        'item_id'     => $item->item_id,
                        'quantity'    => (string) $item->quantity,
                        'unit_price'  => (string) $item->unit_price,
                        'line_number' => (int) $item->line_number,
                    ];
                }

                $event = new SalesOrderConfirmedV1(
                    tenantId: $tenantId,
                    salesOrderId: $order->sales_order_id,
                    orderNumber: $order->order_number,
                    customerId: $order->customer_id,
                    lines: $lines,
                );

                $payload = $event->toPayload();
                $payload['warehouse_id'] = $order->warehouse_id;
                $payload['confirmed_by'] = $userId;

                OutboxPublisher::publish(
                    $tenantId,
                    'sales_orders',
                    $order->sales_order_id,
                    SalesOrderConfirmedV1::EVENT_TYPE,
                    $payload,
                );

                return $order->fresh(['items']);
            });
        } catch (Exception $e) {
            Log::error('Failed to confirm SalesOrder: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getById(string $id): SalesOrder
    {
        $order = SalesOrder::with('items')->find($id);
        if (!$order) {
            throw new NotFoundHttpException('Sales order not found.');
        }

        return $order;
    }
}
