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

        $itemsDto = array_map(function ($item) {
            return new PurchaseReceiptItemDTO(
                itemId: $item['item_id'],
                receivedQuantity: (float) $item['received_quantity'],
                unitPrice: (float) $item['unit_price']
            );
        }, $validated['items']);

        $dto = new CreatePurchaseReceiptDTO(
            purchaseOrderId: $validated['purchase_order_id'] ?? null,
            supplierId: $validated['supplier_id'],
            warehouseId: $validated['warehouse_id'],
            receiptDate: $validated['receipt_date'],
            items: $itemsDto
        );

        $receipt = $this->purchaseReceiptService->createReceipt($dto);

        return response()->json([
            'message' => 'رسید خرید با موفقیت ایجاد شد.',
            'data' => $receipt
        ], 201);
    }
}