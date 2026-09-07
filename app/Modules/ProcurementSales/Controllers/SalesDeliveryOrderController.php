<?php

namespace App\Modules\ProcurementSales\Controllers;

use App\Base\Controller;
use App\Modules\ProcurementSales\DTOs\CreateSalesDeliveryOrderDTO;
use App\Modules\ProcurementSales\DTOs\SalesDeliveryOrderItemDTO;
use App\Modules\ProcurementSales\Requests\CreateSalesDeliveryOrderRequest;
use App\Modules\ProcurementSales\Services\SalesDeliveryOrderService;
use Illuminate\Http\JsonResponse;

class SalesDeliveryOrderController extends Controller
{
    public function __construct(private readonly SalesDeliveryOrderService $deliveryOrderService)
    {
    }

    public function store(CreateSalesDeliveryOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $itemsDto = array_map(function ($item) {
            return new SalesDeliveryOrderItemDTO(
                itemId: $item['item_id'],
                deliveredQuantity: (float) $item['delivered_quantity'],
                unitPrice: (float) ($item['unit_price'] ?? 0),
                orderedQuantity: isset($item['ordered_quantity']) ? (float) $item['ordered_quantity'] : null,
                salesOrderItemId: $item['sales_order_item_id'] ?? null,
                uomCode: $item['uom_code'] ?? null,
                lineNumber: (int) ($item['line_number'] ?? 1),
                notes: $item['notes'] ?? null,
            );
        }, $validated['items']);

        $dto = new CreateSalesDeliveryOrderDTO(
            customerId: $validated['customer_id'],
            warehouseId: $validated['warehouse_id'],
            shippingDate: $validated['shipping_date'],
            salesOrderId: $validated['sales_order_id'] ?? null,
            items: $itemsDto,
            shippingAddress: $validated['shipping_address'] ?? null,
        );

        $deliveryOrder = $this->deliveryOrderService->createDeliveryOrder($dto);

        return response()->json([
            'message' => 'Sales delivery order created successfully.',
            'data'    => $deliveryOrder,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $delivery = $this->deliveryOrderService->getById($id);

        return response()->json(['data' => $delivery]);
    }

    public function post(string $id): JsonResponse
    {
        $delivery = $this->deliveryOrderService->post($id);

        return response()->json([
            'message' => 'Sales delivery posted; inventory issue event published.',
            'data'    => $delivery,
        ]);
    }
}
