<?php

namespace App\Modules\ProcurementSales\Controllers;

use App\Base\Controller;
use App\Modules\ProcurementSales\DTOs\CreatePurchaseOrderDTO;
use App\Modules\ProcurementSales\DTOs\PurchaseOrderItemDTO;
use App\Modules\ProcurementSales\Requests\CreatePurchaseOrderRequest;
use App\Modules\ProcurementSales\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $purchaseOrderService)
    {
    }

    public function store(CreatePurchaseOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $itemsDto = [];
        $line = 1;
        foreach ($validated['items'] as $item) {
            $itemsDto[] = new PurchaseOrderItemDTO(
                itemId: $item['item_id'],
                quantity: (float) $item['quantity'],
                unitPrice: (float) $item['unit_price'],
                discountAmount: (float) ($item['discount_amount'] ?? 0),
                taxAmount: (float) ($item['tax_amount'] ?? 0),
                uomCode: $item['uom_code'] ?? null,
                lineNumber: (int) ($item['line_number'] ?? $line),
                description: $item['description'] ?? null,
            );
            $line++;
        }

        $dto = new CreatePurchaseOrderDTO(
            supplierId: $validated['supplier_id'],
            currencyId: $validated['currency_id'],
            orderDate: $validated['order_date'],
            deliveryDate: $validated['delivery_date'] ?? null,
            items: $itemsDto,
        );

        $purchaseOrder = $this->purchaseOrderService->createPurchaseOrder($dto);

        return response()->json([
            'message' => 'Purchase Order created successfully',
            'data'    => $purchaseOrder,
        ], 201);
    }
}
