<?php

namespace App\Modules\ProcurementSales\Controllers;

use App\Base\Controller;
use App\Modules\ProcurementSales\DTOs\CreatePurchaseReceiptDTO;
use App\Modules\ProcurementSales\DTOs\PurchaseReceiptItemDTO;
use App\Modules\ProcurementSales\Requests\CreatePurchaseReceiptRequest;
use App\Modules\ProcurementSales\Services\PurchaseReceiptService;
use Illuminate\Http\JsonResponse;

class PurchaseReceiptController extends Controller
{
    public function __construct(private readonly PurchaseReceiptService $purchaseReceiptService)
    {
    }

    public function store(CreatePurchaseReceiptRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $itemsDto = [];
        $line = 1;
        foreach ($validated['items'] as $item) {
            $itemsDto[] = new PurchaseReceiptItemDTO(
                itemId: $item['item_id'],
                receivedQuantity: (float) $item['received_quantity'],
                unitPrice: (float) $item['unit_price'],
                orderedQuantity: isset($item['ordered_quantity']) ? (float) $item['ordered_quantity'] : null,
                purchaseOrderItemId: $item['purchase_order_item_id'] ?? null,
                uomCode: $item['uom_code'] ?? null,
                lineNumber: (int) ($item['line_number'] ?? $line),
                notes: $item['notes'] ?? null,
            );
            $line++;
        }

        $dto = new CreatePurchaseReceiptDTO(
            purchaseOrderId: $validated['purchase_order_id'],
            supplierId: $validated['supplier_id'],
            warehouseId: $validated['warehouse_id'],
            receiptDate: $validated['receipt_date'],
            items: $itemsDto,
            notes: $validated['notes'] ?? null,
        );

        $receipt = $this->purchaseReceiptService->createReceipt($dto);

        return response()->json([
            'message' => 'Purchase Receipt created successfully',
            'data'    => $receipt,
        ], 201);
    }

    public function post(string $id): JsonResponse
    {
        $receipt = $this->purchaseReceiptService->postReceipt($id);

        return response()->json([
            'message' => 'Purchase Receipt posted successfully',
            'data'    => $receipt,
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $receipt = $this->purchaseReceiptService->getById($id);

        return response()->json([
            'message' => 'Purchase Receipt retrieved successfully',
            'data'    => $receipt,
        ], 200);
    }
}
