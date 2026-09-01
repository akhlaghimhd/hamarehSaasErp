<?php

namespace App\Modules\DocumentManagement\Controllers;

use App\Base\Controller;
use App\Modules\DocumentManagement\Requests\CreateDocumentRequest;
use App\Modules\DocumentManagement\Requests\UpdateDocumentRequest;
use App\Modules\DocumentManagement\Services\DocumentManagementService;
use Illuminate\Http\JsonResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentManagementService $service)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->service->getAllDocuments()]);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $document = $this->service->getDocumentById($id);
            return response()->json(['data' => $document], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function store(CreateDocumentRequest $request): JsonResponse
    {
        $document = $this->service->createDocument($request->toDTO());
        return response()->json(['message' => 'سند ثبت شد.', 'data' => $document], 201);
    }

    public function update(UpdateDocumentRequest $request, string $id): JsonResponse
    {
        try {
            $document = $this->service->updateDocument($id, $request->toDTO());
            return response()->json(['message' => 'سند با موفقیت ویرایش شد.', 'data' => $document], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->service->deleteDocument($id);
            return response()->json(['message' => 'سند با موفقیت حذف شد.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
