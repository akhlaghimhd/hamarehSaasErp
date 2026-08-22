<?php

namespace App\Modules\ProcurementSales\Services;

use App\Modules\ProcurementSales\DTOs\CreatePurchaseOrderDTO;
use App\Modules\ProcurementSales\Models\PurchaseOrder;
use App\Modules\ProcurementSales\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class PurchaseOrderService
{
    public function createPurchaseOrder(CreatePurchaseOrderDTO $dto): PurchaseOrder
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = app('current_tenant_id'); // استخراج امن از کانتینر
            
            if (!$tenantId) {
                throw new Exception("Tenant Context is missing.");
            }

            // محاسبه مبلغ کل سفارش
            $totalAmount = 0;
            foreach ($dto->items as $item) {
                $totalAmount += ($item->quantity * $item->unitPrice);
            }

            // تولید شماره سفارش (در دنیای واقعی با یک Sequence Generator ساخته می‌شود)
            $orderNumber = 'PO-' . strtoupper(Str::random(6));

            // ایجاد سربرگ سفارش
            $purchaseOrder = PurchaseOrder::create([
                'tenant_id' => $tenantId,
                'supplier_id' => $dto->supplierId,
                'order_number' => $orderNumber,
                'order_date' => $dto->orderDate,
                'expected_delivery_date' => $dto->expectedDeliveryDate,
                'total_amount' => $totalAmount,
                'status' => 1, // 1 = Draft
            ]);

            // ایجاد اقلام سفارش
            $orderItems = [];
            foreach ($dto->items as $itemDto) {
                $orderItems[] = new PurchaseOrderItem([
                    'tenant_id' => $tenantId,
                    'item_id' => $itemDto->itemId,
                    'quantity' => $itemDto->quantity,
                    'unit_price' => $itemDto->unitPrice,
                    'total_price' => $itemDto->quantity * $itemDto->unitPrice,
                ]);
            }
            
            $purchaseOrder->items()->saveMany($orderItems);

            // در اینجا می‌توانیم یک رویداد هم منتشر کنیم (مانند PurchaseOrderCreated)
            // Event(new PurchaseOrderCreated($purchaseOrder));

            return $purchaseOrder->load('items');
        });
    }
}