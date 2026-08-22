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

        $itemsDto = array_map(function ($item) {
            return new PurchaseOrderItemDTO(
                itemId: $item['item_id'],
                quantity: $item['quantity'],
                unitPrice: $item['unit_price']
            );
        }, $validated['items']);

        $dto = new CreatePurchaseOrderDTO(
            supplierId: $validated['supplier_id'],
            orderDate: $validated['order_date'],
            expectedDeliveryDate: $validated['expected_delivery_date'] ?? null,
            items: $itemsDto
        );

        $purchaseOrder = $this->purchaseOrderService->createPurchaseOrder($dto);

        return response()->json([
            'message' => 'Purchase Order created successfully',
            'data' => $purchaseOrder
        ], 201);
    }
}