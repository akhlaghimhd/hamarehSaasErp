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
                unitPrice: (float) $item['unit_price'],
                discountAmount: (float) ($item['discount_amount'] ?? 0),
                taxAmount: (float) ($item['tax_amount'] ?? 0),
                uomCode: $item['uom_code'] ?? null,
                lineNumber: (int) ($item['line_number'] ?? 1),
                description: $item['description'] ?? null,
            );
        }, $validated['items']);

        $dto = new CreateSalesOrderDTO(
            customerId: $validated['customer_id'],
            currencyId: $validated['currency_id'],
            orderDate: $validated['order_date'],
            deliveryDate: $validated['delivery_date'] ?? null,
            warehouseId: $validated['warehouse_id'] ?? null,
            items: $itemsDto,
        );

        $salesOrder = $this->salesOrderService->createSalesOrder($dto);

        return response()->json([
            'message' => 'Sales order created successfully.',
            'data'    => $salesOrder,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $order = $this->salesOrderService->getById($id);

        return response()->json(['data' => $order]);
    }

    public function confirm(string $id): JsonResponse
    {
        $order = $this->salesOrderService->confirm($id);

        return response()->json([
            'message' => 'Sales order confirmed; stock reservation event published.',
            'data'    => $order,
        ]);
    }
}
