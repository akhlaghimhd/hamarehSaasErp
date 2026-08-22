<?php

namespace App\Modules\MasterData\Controllers;

use App\Base\Controller;
use Illuminate\Http\JsonResponse;
use App\Modules\MasterData\Services\MasterDataValueService;
use App\Modules\MasterData\Requests\CreateMasterDataValueRequest;
use App\Modules\MasterData\Requests\UpdateMasterDataValueRequest;
use App\Modules\MasterData\DTOs\CreateMasterDataValueDTO;
use App\Modules\MasterData\DTOs\UpdateMasterDataValueDTO;

class MasterDataValueController extends Controller
{
    public function __construct(private readonly MasterDataValueService $valueService)
    {
    }

    public function index(): JsonResponse
    {
        $values = $this->valueService->getAll();
        return response()->json(['data' => $values]);
    }

    public function show(string $id): JsonResponse
    {
        $value = $this->valueService->getById($id);
        return response()->json(['data' => $value]);
    }

    public function store(CreateMasterDataValueRequest $request): JsonResponse
    {
        $dto = CreateMasterDataValueDTO::fromRequest($request);
        $value = $this->valueService->create($dto);
        
        return response()->json(['data' => $value, 'message' => 'Master Data Value created successfully.'], 201);
    }

    public function update(UpdateMasterDataValueRequest $request, string $id): JsonResponse
    {
        $dto = UpdateMasterDataValueDTO::fromRequest($request);
        $value = $this->valueService->update($id, $dto);
        
        return response()->json(['data' => $value, 'message' => 'Master Data Value updated successfully.']);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->valueService->delete($id);
        return response()->json(['message' => 'Master Data Value deleted successfully.']);
    }
}