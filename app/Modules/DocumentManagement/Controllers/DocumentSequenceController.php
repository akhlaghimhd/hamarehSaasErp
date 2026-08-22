<?php
namespace App\Modules\DocumentManagement\Controllers;
use App\Base\Controller;
use App\Modules\DocumentManagement\Requests\CreateDocumentSequenceRequest;
use App\Modules\DocumentManagement\Services\DocumentManagementService;

class DocumentSequenceController extends Controller {
    public function __construct(private readonly DocumentManagementService $service) {}
    public function store(CreateDocumentSequenceRequest $request) {
        try {
            $sequence = $this->service->createSequence($request->toDTO());
            return response()->json(['message' => 'موتور شماره‌گذاری با موفقیت تنظیم شد.', 'data' => $sequence], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}