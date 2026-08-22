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
                unitPrice: (float) $item['unit_price']
            );
        }, $validated['items']);

        $dto = new CreateSalesDeliveryOrderDTO(
            salesOrderId: $validated['sales_order_id'] ?? null,
            customerId: $validated['customer_id'],
            warehouseId: $validated['warehouse_id'],
            deliveryDate: $validated['delivery_date'],
            items: $itemsDto
        );

        $deliveryOrder = $this->deliveryOrderService->createDeliveryOrder($dto);

        return response()->json([
            'message' => 'حواله خروج فروش با موفقیت ایجاد شد.',
            'data' => $deliveryOrder
        ], 201);
    }
}