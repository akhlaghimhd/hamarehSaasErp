<?php
namespace App\Modules\DocumentManagement\Controllers;
use App\Base\Controller;
use App\Modules\DocumentManagement\Requests\CreateDocumentVersionRequest;
use App\Modules\DocumentManagement\Services\DocumentManagementService;

class DocumentVersionController extends Controller {
    public function __construct(private readonly DocumentManagementService $service) {}
    public function store(CreateDocumentVersionRequest $request) {
        try {
            $version = $this->service->createVersion($request->toDTO());
            return response()->json(['message' => 'نسخه جدید سند با موفقیت ثبت شد.', 'data' => $version], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}