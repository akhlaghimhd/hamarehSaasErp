<?php
namespace App\Modules\DocumentManagement\Controllers;
use App\Base\Controller;
use App\Modules\DocumentManagement\Requests\CreateDocumentRequest;
use App\Modules\DocumentManagement\Services\DocumentManagementService;

class DocumentController extends Controller {
    public function __construct(private readonly DocumentManagementService $service) {}
    public function store(CreateDocumentRequest $request) {
        $document = $this->service->createDocument($request->toDTO());
        return response()->json(['message' => 'سند ثبت شد.', 'data' => $document], 201);
    }
    public function index() {
        return response()->json(['data' => $this->service->getAllDocuments()]);
    }

    public function update(\App\Modules\DocumentManagement\Requests\UpdateDocumentRequest $request, string $id) {
        try {
            $document = $this->service->updateDocument($id, $request->toDTO());
            return response()->json(['message' => 'سند با موفقیت ویرایش شد.', 'data' => $document], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function destroy(string $id) {
        try {
            $this->service->deleteDocument($id);
            return response()->json(['message' => 'سند با موفقیت حذف شد.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}