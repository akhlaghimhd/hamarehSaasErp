<?php

namespace App\Modules\Manufacturing\Controllers;

use App\Base\Controller;
use App\Modules\Manufacturing\Services\MaterialConsumptionService;
use App\Modules\Manufacturing\Services\ProductionOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionOrderController extends Controller
{
    public function __construct(
        private readonly ProductionOrderService $service,
        private readonly MaterialConsumptionService $consumptionService,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->list(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getById($id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_number'     => ['required', 'string', 'max:100'],
            'item_id'          => ['required', 'uuid'],
            'bom_id'           => ['nullable', 'uuid'],
            'planned_quantity' => ['required', 'numeric', 'gt:0'],
            'start_date'       => ['required', 'date'],
            'due_date'         => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $order = $this->service->create($data);

        return response()->json([
            'success' => true,
            'data'    => $order,
        ], 201);
    }

    public function release(string $id): JsonResponse
    {
        $order = $this->service->release($id);

        return response()->json([
            'success' => true,
            'data'    => $order,
        ]);
    }

    public function issueMaterials(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'from_location_id' => ['required', 'uuid'],
            'actuals'          => ['nullable', 'array'],
        ]);

        $lines = $this->consumptionService->issueMaterials(
            $id,
            $data['from_location_id'],
            $data['actuals'] ?? []
        );

        return response()->json([
            'success' => true,
            'data'    => $lines,
        ]);
    }

    public function complete(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'to_location_id'    => ['required', 'uuid'],
            'produced_quantity' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $order = $this->consumptionService->completeProduction(
            $id,
            $data['to_location_id'],
            isset($data['produced_quantity']) ? (float) $data['produced_quantity'] : null
        );

        return response()->json([
            'success' => true,
            'data'    => $order,
        ]);
    }

    public function consumptions(string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->consumptionService->listForOrder($id),
        ]);
    }
}
