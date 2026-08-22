<?php

namespace App\Modules\MasterData\Controllers;

use App\Base\Controller;
use App\Modules\MasterData\Requests\CreateWarehouseRequest;
use App\Modules\MasterData\Requests\UpdateWarehouseRequest;
use App\Modules\MasterData\DTOs\CreateWarehouseDTO;
use App\Modules\MasterData\DTOs\UpdateWarehouseDTO;
use App\Modules\MasterData\Services\WarehouseService;
use Illuminate\Http\JsonResponse;
use Exception;

class WarehouseController extends Controller
{
    public function __construct(
        private readonly WarehouseService $warehouseService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->warehouseService->getAllWarehouses()
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->warehouseService->getWarehouseById($id)
        ], 200);
    }

    public function store(CreateWarehouseRequest $request): JsonResponse
    {
        try {
            $dto = CreateWarehouseDTO::fromArray($request->validated(), '');
            $warehouse = $this->warehouseService->createWarehouse($dto);

            return response()->json([
                'success' => true,
                'message' => 'انبار با موفقیت ثبت شد.',
                'data'    => $warehouse
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطایی در ثبت انبار رخ داد.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function update(UpdateWarehouseRequest $request, string $id): JsonResponse
    {
        try {
            $dto = UpdateWarehouseDTO::fromRequest($request->validated());
            $warehouse = $this->warehouseService->updateWarehouse($id, $dto);

            return response()->json([
                'success' => true,
                'message' => 'اطلاعات انبار با موفقیت ویرایش شد.',
                'data'    => $warehouse
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطایی در ویرایش انبار رخ داد.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->warehouseService->deleteWarehouse($id);

            return response()->json([
                'success' => true,
                'message' => 'انبار با موفقیت حذف شد.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطایی در حذف انبار رخ داد.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}