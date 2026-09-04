<?php

namespace App\Modules\Inventory\Controllers;

use App\Base\Controller;
use App\Modules\Inventory\Services\InventoryDocumentService;
use App\Modules\Inventory\DTOs\CreateInventoryDocumentDTO;
use App\Modules\Inventory\DTOs\UpdateInventoryDocumentDTO;
use App\Modules\Inventory\Requests\CreateInventoryDocumentRequest;
use App\Modules\Inventory\Requests\UpdateInventoryDocumentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class InventoryDocumentController extends Controller
{
    public function __construct(
        protected InventoryDocumentService $documentService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $documentType = $request->query('document_type');
        $status = $request->query('status');

        $documents = $this->documentService->getAllDocuments(
            $documentType !== null ? (int) $documentType : null,
            $status !== null ? (int) $status : null,
        );

        return response()->json([
            'success' => true,
            'data'    => $documents,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $document = $this->documentService->getDocumentById($id);

        return response()->json([
            'success' => true,
            'data'    => $document,
        ]);
    }

    public function store(CreateInventoryDocumentRequest $request): JsonResponse
    {
        $dto = CreateInventoryDocumentDTO::fromArray($request->validated());
        $document = $this->documentService->createDocument($dto);

        return response()->json([
            'success' => true,
            'data'    => $document,
        ], 201);
    }

    public function update(UpdateInventoryDocumentRequest $request, string $id): JsonResponse
    {
        try {
            $dto = UpdateInventoryDocumentDTO::fromArray($request->validated());
            $document = $this->documentService->updateDocument($id, $dto);

            return response()->json([
                'success' => true,
                'data'    => $document,
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
            $this->documentService->deleteDocument($id);

            return response()->json([
                'success' => true,
                'message' => 'Inventory document soft-deleted successfully.',
            ]);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }
}
