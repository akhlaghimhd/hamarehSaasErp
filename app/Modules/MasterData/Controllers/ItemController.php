<?php

namespace App\Modules\MasterData\Controllers;

use App\Base\Controller;
use App\Modules\MasterData\Requests\CreateItemRequest;
use App\Modules\MasterData\Requests\UpdateItemRequest;
use App\Modules\MasterData\DTOs\CreateItemDTO;
use App\Modules\MasterData\DTOs\UpdateItemDTO;
use App\Modules\MasterData\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Exception;

class ItemController extends Controller
{
    public function __construct(
        private readonly ItemService $itemService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->itemService->getAllItems()
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->itemService->getItemById($id)
        ], 200);
    }

    public function store(CreateItemRequest $request): JsonResponse
    {
        try {
            $dto = CreateItemDTO::fromRequest($request->validated());
            $item = $this->itemService->createItem($dto);

            return response()->json([
                'success' => true,
                'message' => 'Item created successfully.',
                'data' => $item
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the item.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(UpdateItemRequest $request, string $id): JsonResponse
    {
        try {
            $dto = UpdateItemDTO::fromRequest($request->validated());
            $item = $this->itemService->updateItem($id, $dto);

            return response()->json([
                'success' => true,
                'message' => 'Item updated successfully.',
                'data' => $item
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the item.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->itemService->deleteItem($id);
            return response()->json([
                'success' => true,
                'message' => 'Item deleted successfully.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the item.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}