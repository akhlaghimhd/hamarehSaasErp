<?php

namespace App\Modules\Manufacturing\Controllers;

use App\Base\Controller;
use App\Modules\Manufacturing\Requests\StoreProductionRoutingRequest;
use App\Modules\Manufacturing\DTOs\ProductionRoutingDTO;
use App\Modules\Manufacturing\Services\ProductionRoutingService;
use Illuminate\Http\JsonResponse;

class ProductionRoutingController extends Controller
{
    public function __construct(
        private readonly ProductionRoutingService $productionRoutingService
    ) {}

    public function store(StoreProductionRoutingRequest $request): JsonResponse
    {
        $dto = ProductionRoutingDTO::fromRequest($request);
        $routing = $this->productionRoutingService->createRouting($dto);

        return response()->json([
            'message' => 'Production Routing successfully created.',
            'data' => $routing
        ], 201);
    }
}