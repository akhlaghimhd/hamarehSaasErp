<?php

namespace App\Modules\ProcurementSales\Services;

use App\Modules\ProcurementSales\DTOs\CreateSalesDeliveryOrderDTO;
use App\Modules\ProcurementSales\Models\SalesDeliveryOrder;
use App\Modules\ProcurementSales\Models\SalesDeliveryOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class SalesDeliveryOrderService
{
    public function createDeliveryOrder(CreateSalesDeliveryOrderDTO $dto): SalesDeliveryOrder
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = app('current_tenant_id');
            if (!$tenantId) {
                throw new Exception("Tenant Context is missing.");
            }

            // محاسبه مبلغ کل حواله خروج
            $totalAmount = 0;
            foreach ($dto->items as $item) {
                $totalAmount += ($item->deliveredQuantity * $item->unitPrice);
            }

            // تولید شماره حواله خروج یکتا
            $deliveryNumber = 'SDO-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

            // ثبت سربرگ حواله
            $deliveryOrder = SalesDeliveryOrder::create([
                'tenant_id' => $tenantId,
                'sales_order_id' => $dto->salesOrderId,
                'customer_id' => $dto->customerId,
                'warehouse_id' => $dto->warehouseId,
                'delivery_number' => $deliveryNumber,
                'delivery_date' => $dto->deliveryDate,
                'total_amount' => $totalAmount,
                'status' => 1, // 1: Draft (در انتظار تایید برای خروج فیزیکی)
                'row_version' => 1
            ]);

            // ثبت اقلام حواله
            $deliveryItems = [];
            foreach ($dto->items as $itemDto) {
                $deliveryItems[] = new SalesDeliveryOrderItem([
                    'tenant_id' => $tenantId,
                    'item_id' => $itemDto->itemId,
                    'delivered_quantity' => $itemDto->deliveredQuantity,
                    'unit_price' => $itemDto->unitPrice,
                    'total_price' => $itemDto->deliveredQuantity * $itemDto->unitPrice,
                ]);
            }
            $deliveryOrder->items()->saveMany($deliveryItems);

            // ثبت رویداد در event_outbox
            // ماژول انبار (Inventory) با شنیدن این رویداد، موجودی کالا را کسر می‌کند
            DB::table('event_outbox')->insert([
                'tenant_id' => $tenantId,
                'aggregate_type' => 'sales_delivery_orders',
                'aggregate_id' => $deliveryOrder->delivery_id,
                'event_type' => 'sales.delivery.created',
                'payload' => json_encode([
                    'delivery_id' => $deliveryOrder->delivery_id,
                    'warehouse_id' => $deliveryOrder->warehouse_id,
                    'sales_order_id' => $deliveryOrder->sales_order_id,
                    'status' => $deliveryOrder->status
                ]),
                'status' => 1, // Pending
                'retry_count' => 0,
                'created_at' => now(),
            ]);

            return $deliveryOrder->load('items');
        });
    }
}