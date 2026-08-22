<?php

namespace App\Modules\Manufacturing\Controllers;

use App\Base\Controller;
use App\Modules\Manufacturing\Requests\StoreProductionOrderRequest;
use App\Modules\Manufacturing\DTOs\ProductionOrderDTO;
use App\Modules\Manufacturing\Services\ProductionOrderService;
use Illuminate\Http\JsonResponse;

class ProductionOrderController extends Controller
{
    public function __construct(
        private readonly ProductionOrderService $productionOrderService
    ) {}

    public function store(StoreProductionOrderRequest $request): JsonResponse
    {
        $dto = ProductionOrderDTO::fromRequest($request);
        $productionOrder = $this->productionOrderService->createProductionOrder($dto);

        return response()->json([
            'message' => 'Production Order successfully created.',
            'data' => $productionOrder
        ], 201);
    }
}