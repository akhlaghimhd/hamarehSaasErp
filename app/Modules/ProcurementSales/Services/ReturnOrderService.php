<?php

namespace App\Modules\ProcurementSales\Services;

use App\Modules\ProcurementSales\DTOs\CreateReturnOrderDTO;
use App\Modules\ProcurementSales\Models\ReturnOrder;
use App\Modules\ProcurementSales\Models\ReturnOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class ReturnOrderService
{
    public function createReturnOrder(CreateReturnOrderDTO $dto): ReturnOrder
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = app('current_tenant_id');
            if (!$tenantId) throw new Exception("Tenant Context is missing.");

            $totalAmount = 0;
            foreach ($dto->items as $item) {
                $totalAmount += ($item->quantity * $item->unitPrice);
            }

            $returnNumber = 'RET-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

            $returnOrder = ReturnOrder::create([
                'tenant_id' => $tenantId,
                'business_partner_id' => $dto->businessPartnerId,
                'source_document_type' => $dto->sourceDocumentType,
                'source_document_id' => $dto->sourceDocumentId,
                'return_number' => $returnNumber,
                'return_date' => $dto->returnDate,
                'total_amount' => $totalAmount,
                'status' => 1, // Pending
                'row_version' => 1
            ]);

            $returnItems = [];
            foreach ($dto->items as $itemDto) {
                $returnItems[] = new ReturnOrderItem([
                    'tenant_id' => $tenantId,
                    'item_id' => $itemDto->itemId,
                    'quantity' => $itemDto->quantity,
                    'unit_price' => $itemDto->unitPrice,
                    'total_price' => $itemDto->quantity * $itemDto->unitPrice,
                ]);
            }
            $returnOrder->items()->saveMany($returnItems);

            // انتشار رویداد برای ماژول انبار جهت بازگشت موجودی
            DB::table('event_outbox')->insert([
                'tenant_id' => $tenantId,
                'aggregate_type' => 'return_orders',
                'aggregate_id' => $returnOrder->return_order_id,
                'event_type' => 'sales.return.created',
                'payload' => json_encode([
                    'return_order_id' => $returnOrder->return_order_id,
                    'source_type' => $returnOrder->source_document_type,
                    'status' => $returnOrder->status
                ]),
                'status' => 1,
                'retry_count' => 0,
                'created_at' => now(),
            ]);

            return $returnOrder->load('items');
        });
    }
}