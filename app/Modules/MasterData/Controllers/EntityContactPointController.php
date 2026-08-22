<?php

namespace App\Modules\MasterData\Controllers;

use App\Base\Controller;
use Illuminate\Http\JsonResponse;
use App\Modules\MasterData\Services\EntityContactPointService;
use App\Modules\MasterData\Requests\CreateEntityContactPointRequest;
use App\Modules\MasterData\Requests\UpdateEntityContactPointRequest;
use App\Modules\MasterData\DTOs\CreateEntityContactPointDTO;
use App\Modules\MasterData\DTOs\UpdateEntityContactPointDTO;

class EntityContactPointController extends Controller
{
    public function __construct(private readonly EntityContactPointService $contactService)
    {
    }

    public function index(): JsonResponse
    {
        $contacts = $this->contactService->getAll();
        return response()->json(['data' => $contacts]);
    }

    public function show(string $id): JsonResponse
    {
        $contact = $this->contactService->getById($id);
        return response()->json(['data' => $contact]);
    }

    public function store(CreateEntityContactPointRequest $request): JsonResponse
    {
        $dto = CreateEntityContactPointDTO::fromRequest($request);
        $contact = $this->contactService->create($dto);
        
        return response()->json(['data' => $contact, 'message' => 'Contact Point created successfully.'], 201);
    }

    public function update(UpdateEntityContactPointRequest $request, string $id): JsonResponse
    {
        $dto = UpdateEntityContactPointDTO::fromRequest($request);
        $contact = $this->contactService->update($id, $dto);
        
        return response()->json(['data' => $contact, 'message' => 'Contact Point updated successfully.']);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->contactService->delete($id);
        return response()->json(['message' => 'Contact Point deleted successfully.']);
    }
}