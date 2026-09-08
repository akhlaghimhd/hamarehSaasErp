<?php

namespace App\Modules\ProcurementSales\Controllers;

use App\Base\Controller;
use App\Modules\ProcurementSales\DTOs\CreatePurchaseRequisitionDTO;
use App\Modules\ProcurementSales\DTOs\PurchaseRequisitionItemDTO;
use App\Modules\ProcurementSales\Requests\CreatePurchaseRequisitionRequest;
use App\Modules\ProcurementSales\Services\PurchaseRequisitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseRequisitionController extends Controller
{
    public function __construct(private readonly PurchaseRequisitionService $service) {}

    public function store(CreatePurchaseRequisitionRequest $request): JsonResponse
    {
        $v = $request->validated();
        $items = array_map(fn ($i) => new PurchaseRequisitionItemDTO(
            itemId: $i['item_id'],
            quantity: (float) $i['quantity'],
            estimatedUnitPrice: (float) ($i['estimated_unit_price'] ?? 0),
            uomCode: $i['uom_code'] ?? null,
            lineNumber: (int) ($i['line_number'] ?? 1),
            description: $i['description'] ?? null,
        ), $v['items']);

        $dto = new CreatePurchaseRequisitionDTO(
            departmentId: $v['department_id'],
            requiredDate: $v['required_date'],
            priority: (int) ($v['priority'] ?? 2),
            description: $v['description'] ?? null,
            items: $items,
        );

        return response()->json(['data' => $this->service->create($dto)], 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => $this->service->getById($id)]);
    }

    public function submit(string $id): JsonResponse
    {
        return response()->json(['data' => $this->service->submit($id)]);
    }

    public function approve(string $id): JsonResponse
    {
        return response()->json(['data' => $this->service->approve($id)]);
    }

    public function reject(string $id): JsonResponse
    {
        return response()->json(['data' => $this->service->reject($id)]);
    }

    public function convertToPurchaseOrder(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|uuid',
            'currency_id' => 'required|uuid',
            'delivery_date' => 'nullable|date',
        ]);
        $order = $this->service->convertToPurchaseOrder(
            $id,
            $validated['supplier_id'],
            $validated['currency_id'],
            $validated['delivery_date'] ?? null,
        );
        return response()->json(['data' => $order], 201);
    }
}
