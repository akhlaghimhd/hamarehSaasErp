<?php

namespace App\Modules\ProcurementSales\Controllers;

use App\Base\Controller;
use App\Modules\ProcurementSales\DTOs\CreateSalesOrderDTO;
use App\Modules\ProcurementSales\DTOs\SalesOrderItemDTO;
use App\Modules\ProcurementSales\Requests\CreateSalesOrderRequest;
use App\Modules\ProcurementSales\Services\SalesOrderService;
use Illuminate\Http\JsonResponse;

class SalesOrderController extends Controller
{
    public function __construct(private readonly SalesOrderService $salesOrderService)
    {
    }

    public function store(CreateSalesOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $itemsDto = array_map(function ($item) {
            return new SalesOrderItemDTO(
                itemId: $item['item_id'],
                quantity: (float) $item['quantity'],
                unitPrice: (float) $item['unit_price']
            );
        }, $validated['items']);

        $dto = new CreateSalesOrderDTO(
            customerId: $validated['customer_id'],
            orderDate: $validated['order_date'],
            expectedDeliveryDate: $validated['expected_delivery_date'] ?? null,
            items: $itemsDto
        );

        $salesOrder = $this->salesOrderService->createSalesOrder($dto);

        return response()->json([
            'message' => 'سفارش فروش با موفقیت ایجاد شد.',
            'data' => $salesOrder
        ], 201);
    }
}