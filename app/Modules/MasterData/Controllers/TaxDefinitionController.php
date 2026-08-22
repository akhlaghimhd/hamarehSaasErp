<?php

namespace App\Modules\MasterData\Controllers;

use App\Base\Controller;
use Illuminate\Http\JsonResponse;
use App\Modules\MasterData\Services\TaxDefinitionService;
use App\Modules\MasterData\Requests\CreateTaxDefinitionRequest;
use App\Modules\MasterData\Requests\UpdateTaxDefinitionRequest;
use App\Modules\MasterData\DTOs\CreateTaxDefinitionDTO;
use App\Modules\MasterData\DTOs\UpdateTaxDefinitionDTO;

class TaxDefinitionController extends Controller
{
    public function __construct(private readonly TaxDefinitionService $taxDefinitionService)
    {
    }

    public function index(): JsonResponse
    {
        $definitions = $this->taxDefinitionService->getAll();
        return response()->json(['data' => $definitions]);
    }

    public function show(string $id): JsonResponse
    {
        $definition = $this->taxDefinitionService->getById($id);
        return response()->json(['data' => $definition]);
    }

    public function store(CreateTaxDefinitionRequest $request): JsonResponse
    {
        $dto = CreateTaxDefinitionDTO::fromRequest($request);
        $definition = $this->taxDefinitionService->create($dto);
        
        return response()->json(['data' => $definition, 'message' => 'Tax Definition created successfully.'], 201);
    }

    public function update(UpdateTaxDefinitionRequest $request, string $id): JsonResponse
    {
        $dto = UpdateTaxDefinitionDTO::fromRequest($request);
        $definition = $this->taxDefinitionService->update($id, $dto);
        
        return response()->json(['data' => $definition, 'message' => 'Tax Definition updated successfully.']);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->taxDefinitionService->delete($id);
        return response()->json(['message' => 'Tax Definition deleted successfully.']);
    }
}