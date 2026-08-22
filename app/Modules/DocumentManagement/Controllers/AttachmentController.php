<?php
namespace App\Modules\DocumentManagement\Controllers;
use App\Base\Controller;
use App\Modules\DocumentManagement\Requests\CreateAttachmentRequest;
use App\Modules\DocumentManagement\Services\DocumentManagementService;

class AttachmentController extends Controller {
    public function __construct(private readonly DocumentManagementService $service) {}
    public function store(CreateAttachmentRequest $request) {
        $attachment = $this->service->createAttachment($request->toDTO());
        return response()->json(['message' => 'پیوست ثبت شد.', 'data' => $attachment], 201);
    }
    public function destroy(string $id) {
        try {
            $this->service->deleteAttachment($id);
            return response()->json(['message' => 'پیوست با موفقیت حذف شد.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}