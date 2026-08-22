<?php

namespace App\Modules\MasterData\Controllers;

use App\Base\Controller;
use Illuminate\Http\JsonResponse;
use App\Modules\MasterData\Services\TaxCategoryService;
use App\Modules\MasterData\Requests\CreateTaxCategoryRequest;
use App\Modules\MasterData\Requests\UpdateTaxCategoryRequest;
use App\Modules\MasterData\DTOs\CreateTaxCategoryDTO;
use App\Modules\MasterData\DTOs\UpdateTaxCategoryDTO;

class TaxCategoryController extends Controller
{
    public function __construct(private readonly TaxCategoryService $taxCategoryService)
    {
    }

    public function index(): JsonResponse
    {
        $categories = $this->taxCategoryService->getAll();
        return response()->json(['data' => $categories]);
    }

    public function show(string $id): JsonResponse
    {
        $category = $this->taxCategoryService->getById($id);
        return response()->json(['data' => $category]);
    }

    public function store(CreateTaxCategoryRequest $request): JsonResponse
    {
        $dto = CreateTaxCategoryDTO::fromRequest($request);
        $category = $this->taxCategoryService->create($dto);
        
        return response()->json(['data' => $category, 'message' => 'Tax Category created successfully.'], 201);
    }

    public function update(UpdateTaxCategoryRequest $request, string $id): JsonResponse
    {
        $dto = UpdateTaxCategoryDTO::fromRequest($request);
        $category = $this->taxCategoryService->update($id, $dto);
        
        return response()->json(['data' => $category, 'message' => 'Tax Category updated successfully.']);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->taxCategoryService->delete($id);
        return response()->json(['message' => 'Tax Category deleted successfully.']);
    }
}