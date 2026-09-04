<?php

namespace App\Modules\Inventory\Controllers;

use App\Base\Controller;
use App\Modules\Inventory\Services\LocationService;
use App\Modules\Inventory\DTOs\CreateLocationDTO;
use App\Modules\Inventory\DTOs\UpdateLocationDTO;
use App\Modules\Inventory\Requests\CreateLocationRequest;
use App\Modules\Inventory\Requests\UpdateLocationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function __construct(
        protected LocationService $locationService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $warehouseId = $request->query('warehouse_id');
        $locations = $this->locationService->getAllLocations($warehouseId);

        return response()->json([
            'success' => true,
            'data'    => $locations,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $location = $this->locationService->getLocationById($id);

        return response()->json([
            'success' => true,
            'data'    => $location,
        ]);
    }

    public function store(CreateLocationRequest $request): JsonResponse
    {
        $dto = CreateLocationDTO::fromArray($request->validated());
        $location = $this->locationService->createLocation($dto);

        return response()->json([
            'success' => true,
            'data'    => $location,
        ], 201);
    }

    public function update(UpdateLocationRequest $request, string $id): JsonResponse
    {
        $dto = UpdateLocationDTO::fromArray($request->validated());
        $location = $this->locationService->updateLocation($id, $dto);

        return response()->json([
            'success' => true,
            'data'    => $location,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->locationService->deleteLocation($id);

        return response()->json([
            'success' => true,
            'message' => 'Location soft-deleted successfully.',
        ]);
    }
}
