<?php

namespace App\Modules\Manufacturing\Controllers;

use App\Base\Controller;
use App\Modules\Manufacturing\Services\ProductionRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionRoutingController extends Controller
{
    public function __construct(
        private readonly ProductionRoutingService $service
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
            'work_center_id'            => ['required', 'uuid'],
            'operation_sequence'        => ['required', 'integer', 'min:1'],
            'operation_name'            => ['required', 'string', 'max:200'],
            'standard_setup_time_hours' => ['nullable', 'numeric', 'min:0'],
            'standard_run_time_hours'   => ['nullable', 'numeric', 'min:0'],
        ]);

        $step = $this->service->addStep($productionOrderId, $data);

        return response()->json(['success' => true, 'data' => $step], 201);
    }

    public function start(string $routingId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->start($routingId),
        ]);
    }

    public function complete(string $routingId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->complete($routingId),
        ]);
    }
}
