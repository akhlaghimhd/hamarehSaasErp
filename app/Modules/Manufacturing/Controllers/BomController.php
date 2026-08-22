<?php

namespace App\Modules\Manufacturing\Controllers;

use App\Base\Controller;
use App\Modules\Manufacturing\Requests\StoreBomRequest;
use App\Modules\Manufacturing\Services\BomService;
use Illuminate\Http\JsonResponse;

class BomController extends Controller
{
    public function __construct(private readonly BomService $bomService) {}

    public function store(StoreBomRequest $request): JsonResponse
    {
        $bom = $this->bomService->createBom($request->toDTO());

        return response()->json([
            'message' => 'BOM created successfully.',
            'data' => $bom
        ], 201);
    }
}