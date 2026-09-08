<?php

namespace App\Modules\ProcurementSales\Controllers;

use App\Base\Controller;
use App\Modules\ProcurementSales\DTOs\CreateSalesInvoiceDTO;
use App\Modules\ProcurementSales\DTOs\SalesInvoiceItemDTO;
use App\Modules\ProcurementSales\Requests\CreateSalesInvoiceRequest;
use App\Modules\ProcurementSales\Services\SalesInvoiceService;
use Illuminate\Http\JsonResponse;

class SalesInvoiceController extends Controller
{
    public function __construct(private readonly SalesInvoiceService $salesInvoiceService)
    {
    }

    public function store(CreateSalesInvoiceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $itemsDto = array_map(function ($item) {
            return new SalesInvoiceItemDTO(
                itemId: $item['item_id'],
                quantity: (float) $item['quantity'],
                unitPrice: (float) $item['unit_price'],
                discountAmount: (float) ($item['discount_amount'] ?? 0),
                taxAmount: (float) ($item['tax_amount'] ?? 0),
                taxDefinitionId: $item['tax_definition_id'] ?? null,
                uomCode: $item['uom_code'] ?? null,
                lineNumber: (int) ($item['line_number'] ?? 1),
                description: $item['description'] ?? null,
            );
        }, $validated['items']);

        $dto = new CreateSalesInvoiceDTO(
            customerId: $validated['customer_id'],
            currencyId: $validated['currency_id'],
            invoiceDate: $validated['invoice_date'],
            dueDate: $validated['due_date'] ?? null,
            salesOrderId: $validated['sales_order_id'] ?? null,
            taxInvoiceNumber: $validated['tax_invoice_number'] ?? null,
            description: $validated['description'] ?? null,
            items: $itemsDto,
        );

        $invoice = $this->salesInvoiceService->create($dto);
        return response()->json(['data' => $invoice], 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => $this->salesInvoiceService->getById($id)]);
    }

    public function post(string $id): JsonResponse
    {
        $invoice = $this->salesInvoiceService->post($id);
        return response()->json(['data' => $invoice]);
    }

    public function submitForApproval(string $id): JsonResponse
    {
        $invoice = $this->salesInvoiceService->submitForApproval($id);
        return response()->json(['data' => $invoice]);
    }
}
