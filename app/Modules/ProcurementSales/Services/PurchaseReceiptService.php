<?php

namespace App\Modules\ProcurementSales\Services;

use App\Modules\ProcurementSales\DTOs\CreatePurchaseReceiptDTO;
use App\Modules\ProcurementSales\Models\PurchaseReceipt;
use App\Modules\ProcurementSales\Models\PurchaseReceiptItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class PurchaseReceiptService
{
    public function createReceipt(CreatePurchaseReceiptDTO $dto): PurchaseReceipt
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = app('current_tenant_id');
            if (!$tenantId) {
                throw new Exception("Tenant Context is missing.");
            }

            // محاسبه مبلغ کل رسید
            $totalAmount = 0;
            foreach ($dto->items as $item) {
                $totalAmount += ($item->receivedQuantity * $item->unitPrice);
            }

            // تولید شماره رسید یکتا
            $receiptNumber = 'PR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

            // ثبت سربرگ رسید خرید
            $receipt = PurchaseReceipt::create([
                'tenant_id' => $tenantId,
                'purchase_order_id' => $dto->purchaseOrderId,
                'supplier_id' => $dto->supplierId,
                'warehouse_id' => $dto->warehouseId,
                'receipt_number' => $receiptNumber,
                'receipt_date' => $dto->receiptDate,
                'total_amount' => $totalAmount,
                'status' => 1, // 1: Draft (برای قطعی شدن باید Approve شود)
                'row_version' => 1
            ]);

            // ثبت اقلام رسید
            $receiptItems = [];
            foreach ($dto->items as $itemDto) {
                $receiptItems[] = new PurchaseReceiptItem([
                    'tenant_id' => $tenantId,
                    'item_id' => $itemDto->itemId,
                    'received_quantity' => $itemDto->receivedQuantity,
                    'unit_price' => $itemDto->unitPrice,
                    'total_price' => $itemDto->receivedQuantity * $itemDto->unitPrice,
                ]);
            }
            $receipt->items()->saveMany($receiptItems);

            // ثبت رویداد در event_outbox
            // این رویداد در آینده توسط ماژول انبار خوانده شده و موجودی انبار را افزایش می‌دهد
            DB::table('event_outbox')->insert([
                'tenant_id' => $tenantId,
                'aggregate_type' => 'purchase_receipts',
                'aggregate_id' => $receipt->receipt_id,
                'event_type' => 'procurement.receipt.created',
                'payload' => json_encode([
                    'receipt_id' => $receipt->receipt_id,
                    'warehouse_id' => $receipt->warehouse_id,
                    'status' => $receipt->status
                ]),
                'status' => 1, // Pending
                'retry_count' => 0,
                'created_at' => now(),
            ]);

            return $receipt->load('items');
        });
    }
}