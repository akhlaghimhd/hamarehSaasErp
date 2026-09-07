<?php

namespace App\Modules\Manufacturing\Controllers;

use App\Base\Controller;
use App\Modules\Manufacturing\Services\ProductionLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionLogController extends Controller
{
    public function __construct(
        private readonly ProductionLogService $service
    ) {
    }

    public function index(string $productionOrderId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->listForOrder($productionOrderId),
        ]);
    }

    public function store(Request $request, string $productionOrderId): JsonResponse
    {
        $data = $request->validate([
            'log_type'          => ['required', 'integer', 'in:1,2,3'],
            'routing_id'        => ['nullable', 'uuid'],
            'item_id'           => ['nullable', 'uuid'],
            'quantity_consumed' => ['nullable', 'numeric', 'min:0'],
            'hours_spent'       => ['nullable', 'numeric', 'min:0'],
        ]);

        $log = $this->service->log($productionOrderId, $data);

        return response()->json(['success' => true, 'data' => $log], 201);
    }
}
