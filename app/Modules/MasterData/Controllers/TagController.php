<?php

namespace App\Modules\MasterData\Controllers;

use App\Base\Controller;
use Illuminate\Http\JsonResponse;
use App\Modules\MasterData\Services\TagService;
use App\Modules\MasterData\Requests\CreateTagRequest;
use App\Modules\MasterData\Requests\UpdateTagRequest;
use App\Modules\MasterData\DTOs\CreateTagDTO;
use App\Modules\MasterData\DTOs\UpdateTagDTO;

class TagController extends Controller
{
    public function __construct(private readonly TagService $tagService)
    {
    }

    public function index(): JsonResponse
    {
        $tags = $this->tagService->getAll();
        return response()->json(['data' => $tags]);
    }

    public function show(string $id): JsonResponse
    {
        $tag = $this->tagService->getById($id);
        return response()->json(['data' => $tag]);
    }

    public function store(CreateTagRequest $request): JsonResponse
    {
        $dto = CreateTagDTO::fromRequest($request);
        $tag = $this->tagService->create($dto);
        
        return response()->json(['data' => $tag, 'message' => 'Tag created successfully.'], 201);
    }

    public function update(UpdateTagRequest $request, string $id): JsonResponse
    {
        $dto = UpdateTagDTO::fromRequest($request);
        $tag = $this->tagService->update($id, $dto);
        
        return response()->json(['data' => $tag, 'message' => 'Tag updated successfully.']);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->tagService->delete($id);
        return response()->json(['message' => 'Tag deleted successfully.']);
    }
}