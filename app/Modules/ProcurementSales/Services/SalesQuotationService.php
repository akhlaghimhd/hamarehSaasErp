<?php

namespace App\Modules\ProcurementSales\Services;

use App\Modules\ProcurementSales\DTOs\CreateSalesQuotationDTO;
use App\Modules\ProcurementSales\Models\SalesQuotation;
use App\Modules\ProcurementSales\Models\SalesQuotationItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class SalesQuotationService
{
    public function createQuotation(CreateSalesQuotationDTO $dto): SalesQuotation
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = app('current_tenant_id');
            if (!$tenantId) throw new Exception("Tenant Context is missing.");

            $totalAmount = 0;
            foreach ($dto->items as $item) {
                $totalAmount += ($item->quantity * $item->unitPrice);
            }

            $quotationNumber = 'SQ-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

            $quotation = SalesQuotation::create([
                'tenant_id' => $tenantId,
                'customer_id' => $dto->customerId,
                'quotation_number' => $quotationNumber,
                'quotation_date' => $dto->quotationDate,
                'valid_until' => $dto->validUntil,
                'total_amount' => $totalAmount,
                'status' => 1, // Draft
                'row_version' => 1
            ]);

            $quotationItems = [];
            foreach ($dto->items as $itemDto) {
                $quotationItems[] = new SalesQuotationItem([
                    'tenant_id' => $tenantId,
                    'item_id' => $itemDto->itemId,
                    'quantity' => $itemDto->quantity,
                    'unit_price' => $itemDto->unitPrice,
                    'total_price' => $itemDto->quantity * $itemDto->unitPrice,
                ]);
            }
            $quotation->items()->saveMany($quotationItems);

            return $quotation->load('items');
        });
    }
}