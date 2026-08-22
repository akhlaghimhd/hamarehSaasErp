<?php

namespace App\Modules\MasterData\Controllers;

use App\Base\Controller;
use Illuminate\Http\JsonResponse;
use App\Modules\MasterData\Services\UnitOfMeasureService;
use App\Modules\MasterData\Requests\CreateUnitOfMeasureRequest;
use App\Modules\MasterData\Requests\UpdateUnitOfMeasureRequest;
use App\Modules\MasterData\DTOs\CreateUnitOfMeasureDTO;
use App\Modules\MasterData\DTOs\UpdateUnitOfMeasureDTO;

class UnitOfMeasureController extends Controller
{
    public function __construct(private readonly UnitOfMeasureService $uomService)
    {
    }

    public function index(): JsonResponse
    {
        $units = $this->uomService->getAll();
        return response()->json(['data' => $units]);
    }

    public function show(string $id): JsonResponse
    {
        $unit = $this->uomService->getById($id);
        return response()->json(['data' => $unit]);
    }

    public function store(CreateUnitOfMeasureRequest $request): JsonResponse
    {
        $dto = CreateUnitOfMeasureDTO::fromRequest($request);
        $unit = $this->uomService->create($dto);
        
        return response()->json(['data' => $unit, 'message' => 'Unit of Measure created successfully.'], 201);
    }

    public function update(UpdateUnitOfMeasureRequest $request, string $id): JsonResponse
    {
        $dto = UpdateUnitOfMeasureDTO::fromRequest($request);
        $unit = $this->uomService->update($id, $dto);
        
        return response()->json(['data' => $unit, 'message' => 'Unit of Measure updated successfully.']);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->uomService->delete($id);
        return response()->json(['message' => 'Unit of Measure deleted successfully.']);
    }
}