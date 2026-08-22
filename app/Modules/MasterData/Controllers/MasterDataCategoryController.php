<?php

namespace App\Modules\MasterData\Controllers;

use App\Base\Controller;
use Illuminate\Http\JsonResponse;
use App\Modules\MasterData\Services\MasterDataCategoryService;
use App\Modules\MasterData\Requests\CreateMasterDataCategoryRequest;
use App\Modules\MasterData\Requests\UpdateMasterDataCategoryRequest;
use App\Modules\MasterData\DTOs\CreateMasterDataCategoryDTO;
use App\Modules\MasterData\DTOs\UpdateMasterDataCategoryDTO;

class MasterDataCategoryController extends Controller
{
    public function __construct(private readonly MasterDataCategoryService $categoryService)
    {
    }

    public function index(): JsonResponse
    {
        $categories = $this->categoryService->getAll();
        return response()->json(['data' => $categories]);
    }

    public function show(string $id): JsonResponse
    {
        $category = $this->categoryService->getById($id);
        return response()->json(['data' => $category]);
    }

    public function store(CreateMasterDataCategoryRequest $request): JsonResponse
    {
        $dto = CreateMasterDataCategoryDTO::fromRequest($request);
        $category = $this->categoryService->create($dto);
        
        return response()->json(['data' => $category, 'message' => 'Category created successfully.'], 201);
    }

    public function update(UpdateMasterDataCategoryRequest $request, string $id): JsonResponse
    {
        $dto = UpdateMasterDataCategoryDTO::fromRequest($request);
        $category = $this->categoryService->update($id, $dto);
        
        return response()->json(['data' => $category, 'message' => 'Category updated successfully.']);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->categoryService->delete($id);
        return response()->json(['message' => 'Category deleted successfully.']);
    }
}