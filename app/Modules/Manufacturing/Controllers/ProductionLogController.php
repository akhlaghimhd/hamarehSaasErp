<?php

namespace App\Modules\Manufacturing\Controllers;

use App\Base\Controller;
use App\Modules\Manufacturing\Requests\StoreProductionLogRequest;
use App\Modules\Manufacturing\DTOs\ProductionLogDTO;
use App\Modules\Manufacturing\Services\ProductionLogService;
use Illuminate\Http\JsonResponse;

class ProductionLogController extends Controller
{
    public function __construct(
        private readonly ProductionLogService $productionLogService
    ) {}

    public function store(StoreProductionLogRequest $request): JsonResponse
    {
        $dto = ProductionLogDTO::fromRequest($request);
        $log = $this->productionLogService->createLog($dto);

        return response()->json([
            'message' => 'Production Log successfully recorded.',
            'data' => $log
        ], 201);
    }
}