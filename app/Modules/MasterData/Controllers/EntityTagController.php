<?php

namespace App\Modules\MasterData\Controllers;

use App\Base\Controller;
use Illuminate\Http\JsonResponse;
use App\Modules\MasterData\Services\EntityTagService;
use App\Modules\MasterData\Requests\CreateEntityTagRequest;
use App\Modules\MasterData\Requests\UpdateEntityTagRequest;
use App\Modules\MasterData\DTOs\CreateEntityTagDTO;
use App\Modules\MasterData\DTOs\UpdateEntityTagDTO;

class EntityTagController extends Controller
{
    public function __construct(private readonly EntityTagService $entityTagService)
    {
    }

    public function index(): JsonResponse
    {
        $entityTags = $this->entityTagService->getAll();
        return response()->json(['data' => $entityTags]);
    }

    public function store(CreateEntityTagRequest $request): JsonResponse
    {
        $dto = CreateEntityTagDTO::fromRequest($request);
        $entityTag = $this->entityTagService->assignTag($dto); // متد اختصاصی برای Pivot
        
        return response()->json(['data' => $entityTag, 'message' => 'Tag assigned to entity successfully.'], 201);
    }

    public function destroy(string $id): JsonResponse
    {
        // در جداول Pivot معمولاً شناسه انتساب حذف می‌شود
        $this->entityTagService->removeTag($id);
        return response()->json(['message' => 'Tag removed from entity successfully.']);
    }
}