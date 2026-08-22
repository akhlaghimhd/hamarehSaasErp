<?php

namespace App\Modules\ProcurementSales\Services;

use App\Modules\ProcurementSales\DTOs\CreateSalesOrderDTO;
use App\Modules\ProcurementSales\Models\SalesOrder;
use App\Modules\ProcurementSales\Models\SalesOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class SalesOrderService
{
    public function createSalesOrder(CreateSalesOrderDTO $dto): SalesOrder
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = app('current_tenant_id');
            if (!$tenantId) {
                throw new Exception("Tenant Context is missing.");
            }

            // محاسبه مبلغ کل سفارش فروش
            $totalAmount = 0;
            foreach ($dto->items as $item) {
                $totalAmount += ($item->quantity * $item->unitPrice);
            }

            // تولید شماره سفارش فروش یکتا
            $orderNumber = 'SO-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

            // ثبت سربرگ سفارش فروش
            $salesOrder = SalesOrder::create([
                'tenant_id' => $tenantId,
                'customer_id' => $dto->customerId,
                'order_number' => $orderNumber,
                'order_date' => $dto->orderDate,
                'expected_delivery_date' => $dto->expectedDeliveryDate,
                'total_amount' => $totalAmount,
                'status' => 1, // 1: Draft
                'row_version' => 1
            ]);

            // ثبت اقلام سفارش
            $orderItems = [];
            foreach ($dto->items as $itemDto) {
                $orderItems[] = new SalesOrderItem([
                    'tenant_id' => $tenantId,
                    'item_id' => $itemDto->itemId,
                    'quantity' => $itemDto->quantity,
                    'unit_price' => $itemDto->unitPrice,
                    'total_price' => $itemDto->quantity * $itemDto->unitPrice,
                ]);
            }
            $salesOrder->items()->saveMany($orderItems);

            // ثبت رویداد در event_outbox
            // ماژول حسابداری یا انبار می‌تواند به این رویداد گوش دهد (مثلاً برای رزرو موجودی)
            DB::table('event_outbox')->insert([
                'tenant_id' => $tenantId,
                'aggregate_type' => 'sales_orders',
                'aggregate_id' => $salesOrder->sales_order_id,
                'event_type' => 'sales.order.created',
                'payload' => json_encode([
                    'sales_order_id' => $salesOrder->sales_order_id,
                    'customer_id' => $salesOrder->customer_id,
                    'total_amount' => $salesOrder->total_amount,
                    'status' => $salesOrder->status
                ]),
                'status' => 1, // Pending
                'retry_count' => 0,
                'created_at' => now(),
            ]);

            return $salesOrder->load('items');
        });
    }
}