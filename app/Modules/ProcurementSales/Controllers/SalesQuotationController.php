<?php

namespace App\Modules\ProcurementSales\Controllers;

use App\Base\Controller;
use App\Modules\ProcurementSales\DTOs\CreateSalesQuotationDTO;
use App\Modules\ProcurementSales\DTOs\SalesQuotationItemDTO;
use App\Modules\ProcurementSales\Requests\CreateSalesQuotationRequest;
use App\Modules\ProcurementSales\Services\SalesQuotationService;
use Illuminate\Http\JsonResponse;

class SalesQuotationController extends Controller
{
    public function __construct(private readonly SalesQuotationService $service) {}

    public function store(CreateSalesQuotationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $itemsDto = array_map(fn($item) => new SalesQuotationItemDTO(
            itemId: $item['item_id'], quantity: (float)$item['quantity'], unitPrice: (float)$item['unit_price']
        ), $validated['items']);

        $dto = new CreateSalesQuotationDTO(
            customerId: $validated['customer_id'],
            quotationDate: $validated['quotation_date'],
            validUntil: $validated['valid_until'] ?? null,
            items: $itemsDto
        );

        $quotation = $this->service->createQuotation($dto);
        return response()->json(['message' => 'پیش‌فاکتور با موفقیت ایجاد شد.', 'data' => $quotation], 201);
    }
}