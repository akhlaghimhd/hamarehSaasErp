<?php

namespace App\Modules\ProcurementSales\Controllers;

use App\Base\Controller;
use App\Modules\ProcurementSales\DTOs\CreatePurchaseInvoiceDTO;
use App\Modules\ProcurementSales\DTOs\PurchaseInvoiceItemDTO;
use App\Modules\ProcurementSales\Requests\CreatePurchaseInvoiceRequest;
use App\Modules\ProcurementSales\Services\PurchaseInvoiceService;
use Illuminate\Http\JsonResponse;

class PurchaseInvoiceController extends Controller
{
    public function __construct(private readonly PurchaseInvoiceService $purchaseInvoiceService)
    {
    }

    public function store(CreatePurchaseInvoiceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $itemsDto = array_map(function ($item) {
            return new PurchaseInvoiceItemDTO(
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

        $dto = new CreatePurchaseInvoiceDTO(
            supplierId: $validated['supplier_id'],
            currencyId: $validated['currency_id'],
            invoiceDate: $validated['invoice_date'],
            dueDate: $validated['due_date'] ?? null,
            purchaseOrderId: $validated['purchase_order_id'] ?? null,
            supplierInvoiceRef: $validated['supplier_invoice_ref'] ?? null,
            taxInvoiceNumber: $validated['tax_invoice_number'] ?? null,
            description: $validated['description'] ?? null,
            items: $itemsDto,
        );

        $invoice = $this->purchaseInvoiceService->create($dto);

        return response()->json([
            'message' => 'Purchase invoice created successfully.',
            'data'    => $invoice,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $invoice = $this->purchaseInvoiceService->getById($id);

        return response()->json(['data' => $invoice]);
    }

    public function post(string $id): JsonResponse
    {
        $invoice = $this->purchaseInvoiceService->post($id);

        return response()->json([
            'message' => 'Purchase invoice posted; AP clearing voucher created when accounts exist.',
            'data'    => $invoice,
        ]);
    }
}
