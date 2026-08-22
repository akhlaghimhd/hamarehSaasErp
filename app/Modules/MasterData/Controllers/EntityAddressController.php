<?php

namespace App\Modules\MasterData\Controllers;

use App\Base\Controller;
use Illuminate\Http\JsonResponse;
use App\Modules\MasterData\Services\EntityAddressService;
use App\Modules\MasterData\Requests\CreateEntityAddressRequest;
use App\Modules\MasterData\Requests\UpdateEntityAddressRequest;
use App\Modules\MasterData\DTOs\CreateEntityAddressDTO;
use App\Modules\MasterData\DTOs\UpdateEntityAddressDTO;

class EntityAddressController extends Controller
{
    public function __construct(private readonly EntityAddressService $addressService)
    {
    }

    public function index(): JsonResponse
    {
        $addresses = $this->addressService->getAll();
        return response()->json(['data' => $addresses]);
    }

    public function show(string $id): JsonResponse
    {
        $address = $this->addressService->getById($id);
        return response()->json(['data' => $address]);
    }

    public function store(CreateEntityAddressRequest $request): JsonResponse
    {
        $dto = CreateEntityAddressDTO::fromRequest($request);
        $address = $this->addressService->create($dto);
        
        return response()->json(['data' => $address, 'message' => 'Entity Address created successfully.'], 201);
    }

    public function update(UpdateEntityAddressRequest $request, string $id): JsonResponse
    {
        $dto = UpdateEntityAddressDTO::fromRequest($request);
        $address = $this->addressService->update($id, $dto);
        
        return response()->json(['data' => $address, 'message' => 'Entity Address updated successfully.']);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->addressService->delete($id);
        return response()->json(['message' => 'Entity Address deleted successfully.']);
    }
}