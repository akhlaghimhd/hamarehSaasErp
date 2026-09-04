<?php

namespace App\Modules\Inventory\Controllers;

use App\Base\Controller;
use App\Modules\Inventory\Services\InventoryDocumentItemService;
use App\Modules\Inventory\DTOs\CreateInventoryDocumentItemDTO;
use App\Modules\Inventory\DTOs\UpdateInventoryDocumentItemDTO;
use App\Modules\Inventory\Requests\CreateInventoryDocumentItemRequest;
use App\Modules\Inventory\Requests\UpdateInventoryDocumentItemRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class InventoryDocumentItemController extends Controller
{
    public function __construct(
        protected InventoryDocumentItemService $itemService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $documentId = $request->query('document_id');
        $items = $this->itemService->getAllItems($documentId);

        return response()->json([
            'success' => true,
            'data'    => $items,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $item = $this->itemService->getItemById($id);

        return response()->json([
            'success' => true,
            'data'    => $item,
        ]);
    }

    public function store(CreateInventoryDocumentItemRequest $request): JsonResponse
    {
        try {
            $dto = CreateInventoryDocumentItemDTO::fromArray($request->validated());
            $item = $this->itemService->createItem($dto);

            return response()->json([
                'success' => true,
                'data'    => $item,
            ], 201);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    public function update(UpdateInventoryDocumentItemRequest $request, string $id): JsonResponse
    {
        try {
            $dto = UpdateInventoryDocumentItemDTO::fromArray($request->validated());
            $item = $this->itemService->updateItem($id, $dto);

            return response()->json([
                'success' => true,
                'data'    => $item,
            ]);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->itemService->deleteItem($id);

            return response()->json([
                'success' => true,
                'message' => 'Document item deleted successfully.',
            ]);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }
}
