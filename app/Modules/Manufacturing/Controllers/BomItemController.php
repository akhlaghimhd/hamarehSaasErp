<?php

namespace App\Modules\Manufacturing\Controllers;

use App\Base\Controller;
use App\Modules\Manufacturing\Requests\StoreBomItemRequest;
use App\Modules\Manufacturing\DTOs\BomItemDTO;
use App\Modules\Manufacturing\Services\BomItemService;
use Illuminate\Http\JsonResponse;

class BomItemController extends Controller
{
    public function __construct(
        private readonly BomItemService $bomItemService
    ) {}

    public function store(StoreBomItemRequest $request): JsonResponse
    {
        $dto = BomItemDTO::fromRequest($request);
        $bomItem = $this->bomItemService->addBomItem($dto);

        return response()->json([
            'message' => 'Material successfully added to BOM.',
            'data' => $bomItem
        ], 201);
    }
}