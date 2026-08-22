<?php

namespace App\Modules\ProcurementSales\Controllers;

use App\Base\Controller;
use App\Modules\ProcurementSales\DTOs\CreateReturnOrderDTO;
use App\Modules\ProcurementSales\DTOs\ReturnOrderItemDTO;
use App\Modules\ProcurementSales\Requests\CreateReturnOrderRequest;
use App\Modules\ProcurementSales\Services\ReturnOrderService;
use Illuminate\Http\JsonResponse;

class ReturnOrderController extends Controller
{
    public function __construct(private readonly ReturnOrderService $service) {}

    public function store(CreateReturnOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $itemsDto = array_map(fn($item) => new ReturnOrderItemDTO(
            itemId: $item['item_id'], quantity: (float)$item['quantity'], unitPrice: (float)$item['unit_price']
        ), $validated['items']);

        $dto = new CreateReturnOrderDTO(
            businessPartnerId: $validated['business_partner_id'],
            sourceDocumentType: $validated['source_document_type'],
            sourceDocumentId: $validated['source_document_id'],
            returnDate: $validated['return_date'],
            items: $itemsDto
        );

        $returnOrder = $this->service->createReturnOrder($dto);
        return response()->json(['message' => 'سند مرجوعی با موفقیت ایجاد شد.', 'data' => $returnOrder], 201);
    }
}