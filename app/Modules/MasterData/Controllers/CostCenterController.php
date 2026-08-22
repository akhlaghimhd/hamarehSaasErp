<?php

namespace App\Modules\MasterData\Controllers;

use App\Base\Controller;
use App\Modules\MasterData\Requests\CreateCostCenterRequest;
use App\Modules\MasterData\Requests\UpdateCostCenterRequest;
use App\Modules\MasterData\DTOs\CreateCostCenterDTO;
use App\Modules\MasterData\DTOs\UpdateCostCenterDTO;
use App\Modules\MasterData\Services\CostCenterService;
use Illuminate\Http\JsonResponse;
use Exception;

class CostCenterController extends Controller
{
    public function __construct(
        private readonly CostCenterService $costCenterService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->costCenterService->getAllCostCenters()
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->costCenterService->getCostCenterById($id)
        ], 200);
    }

    public function store(CreateCostCenterRequest $request): JsonResponse
    {
        try {
            $dto = CreateCostCenterDTO::fromArray($request->validated(), '');
            $costCenter = $this->costCenterService->createCostCenter($dto);

            return response()->json([
                'success' => true,
                'message' => 'ساختار سازمانی / مرکز هزینه با موفقیت ایجاد شد.',
                'data'    => $costCenter
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطایی در ثبت مرکز هزینه رخ داد.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function update(UpdateCostCenterRequest $request, string $id): JsonResponse
    {
        try {
            $dto = UpdateCostCenterDTO::fromRequest($request->validated());
            $costCenter = $this->costCenterService->updateCostCenter($id, $dto);

            return response()->json([
                'success' => true,
                'message' => 'ساختار سازمانی / مرکز هزینه با موفقیت ویرایش شد.',
                'data'    => $costCenter
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطایی در ویرایش مرکز هزینه رخ داد.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->costCenterService->deleteCostCenter($id);

            return response()->json([
                'success' => true,
                'message' => 'مرکز هزینه با موفقیت حذف شد.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطایی در حذف مرکز هزینه رخ داد.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}