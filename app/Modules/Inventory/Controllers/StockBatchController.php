<?php

namespace App\Modules\Inventory\Controllers;

use App\Base\Controller;
use App\Modules\Inventory\Services\StockBatchService;
use App\Modules\Inventory\DTOs\CreateStockBatchDTO;
use App\Modules\Inventory\DTOs\UpdateStockBatchDTO;
use App\Modules\Inventory\Requests\CreateStockBatchRequest;
use App\Modules\Inventory\Requests\UpdateStockBatchRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockBatchController extends Controller
{
    public function __construct(
        protected StockBatchService $stockBatchService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = array_filter([
            'item_id'   => $request->query('item_id'),
            'qc_status' => $request->query('qc_status') !== null
                ? (int) $request->query('qc_status')
                : null,
        ], fn ($v) => $v !== null && $v !== '');

        $batches = $this->stockBatchService->getAllBatches($filters);

        return response()->json([
            'success' => true,
            'data'    => $batches,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $batch = $this->stockBatchService->getBatchById($id);

        return response()->json([
            'success' => true,
            'data'    => $batch,
        ]);
    }

    public function store(CreateStockBatchRequest $request): JsonResponse
    {
        $dto = CreateStockBatchDTO::fromArray($request->validated());
        $batch = $this->stockBatchService->createBatch($dto);

        return response()->json([
            'success' => true,
            'data'    => $batch,
        ], 201);
    }

    public function update(UpdateStockBatchRequest $request, string $id): JsonResponse
    {
        $dto = UpdateStockBatchDTO::fromArray($request->validated());
        $batch = $this->stockBatchService->updateBatch($id, $dto);

        return response()->json([
            'success' => true,
            'data'    => $batch,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->stockBatchService->deleteBatch($id);

        return response()->json([
            'success' => true,
            'message' => 'Stock batch soft-deleted successfully.',
        ]);
    }
}
