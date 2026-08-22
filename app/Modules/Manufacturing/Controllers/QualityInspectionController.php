<?php

namespace App\Modules\Manufacturing\Controllers;

use App\Base\Controller;
use App\Modules\Manufacturing\Requests\StoreQualityInspectionRequest;
use App\Modules\Manufacturing\DTOs\QualityInspectionDTO;
use App\Modules\Manufacturing\Services\QualityInspectionService;
use Illuminate\Http\JsonResponse;

class QualityInspectionController extends Controller
{
    public function __construct(
        private readonly QualityInspectionService $qualityInspectionService
    ) {}

    public function store(StoreQualityInspectionRequest $request): JsonResponse
    {
        $dto = QualityInspectionDTO::fromRequest($request);
        $inspection = $this->qualityInspectionService->recordInspection($dto);

        return response()->json([
            'message' => 'Quality Inspection successfully recorded.',
            'data' => $inspection
        ], 201);
    }
}