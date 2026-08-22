<?php

namespace App\Modules\HrManagement\Controllers;

use App\Base\Controller; // کنترلر پایه سیستم
use App\Modules\HrManagement\Requests\CreateHrDocumentRequest;
use App\Modules\HrManagement\Services\HrDocumentService;
use Illuminate\Http\JsonResponse;

class HrDocumentController extends Controller
{
    public function __construct(
        private readonly HrDocumentService $hrDocumentService
    ) {}

    public function store(CreateHrDocumentRequest $request): JsonResponse
    {
        $dto = $request->toDTO();
        
        $document = $this->hrDocumentService->createDocument($dto);

        return response()->json([
            'success' => true,
            'message' => 'HR Document created successfully.',
            'data' => $document
        ], 201);
    }
}