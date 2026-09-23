<?php

namespace App\Modules\Organization\Controllers;

use App\Base\Controller;
use App\Modules\Organization\Requests\CreateEntityAddressRequest;
use App\Modules\Organization\DTOs\CreateEntityAddressDTO;
use App\Modules\Organization\Services\EntityAddressService;
use Illuminate\Http\JsonResponse;

class EntityAddressController extends Controller
{
    public function __construct(
        private readonly EntityAddressService $entityAddressService
    ) {
    }

    public function index(string $entityType, string $entityId): JsonResponse
    {
        $addresses = $this->entityAddressService->listForEntity(
            strtoupper($entityType),
            $entityId
        );

        return response()->json([
            'status' => 'success',
            'data'   => $addresses,
        ]);
    }

    public function store(CreateEntityAddressRequest $request): JsonResponse
    {
        $dto = CreateEntityAddressDTO::fromRequest($request->validated());
        $address = $this->entityAddressService->create($dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'Address created successfully.',
            'data'    => $address,
        ], 201);
    }

    public function destroy(string $entityAddressId): JsonResponse
    {
        $this->entityAddressService->softDelete($entityAddressId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Address deleted successfully.',
        ]);
    }
}
