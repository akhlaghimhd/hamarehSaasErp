<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Requests\CreateEntityContactPointRequest;
use App\Modules\Organization\DTOs\CreateEntityContactPointDTO;
use App\Modules\Organization\Services\EntityContactPointService;
use Illuminate\Http\JsonResponse;

class EntityContactPointController extends Controller
{
    public function __construct(
        private readonly EntityContactPointService $entityContactPointService
    ) {
    }

    public function index(string $entityType, string $entityId): JsonResponse
    {
        $points = $this->entityContactPointService->listForEntity(
            strtoupper($entityType),
            $entityId
        );

        return response()->json([
            'status' => 'success',
            'data'   => $points,
        ]);
    }

    public function store(CreateEntityContactPointRequest $request): JsonResponse
    {
        $dto = CreateEntityContactPointDTO::fromRequest($request->validated());
        $point = $this->entityContactPointService->create($dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'Contact point created successfully.',
            'data'    => $point,
        ], 201);
    }

    public function destroy(string $entityContactPointId): JsonResponse
    {
        $this->entityContactPointService->softDelete($entityContactPointId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Contact point deleted successfully.',
        ]);
    }
}
