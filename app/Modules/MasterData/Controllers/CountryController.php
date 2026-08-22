<?php

namespace App\Modules\MasterData\Controllers;

use App\Base\Controller;
use Illuminate\Http\JsonResponse;
use App\Modules\MasterData\Services\CountryService;
use App\Modules\MasterData\Requests\CreateCountryRequest;
use App\Modules\MasterData\Requests\UpdateCountryRequest;
use App\Modules\MasterData\DTOs\CreateCountryDTO;
use App\Modules\MasterData\DTOs\UpdateCountryDTO;

class CountryController extends Controller
{
    public function __construct(private readonly CountryService $countryService)
    {
    }

    public function index(): JsonResponse
    {
        $countries = $this->countryService->getAll();
        return response()->json(['data' => $countries]);
    }

    public function show(string $id): JsonResponse
    {
        $country = $this->countryService->getById($id);
        return response()->json(['data' => $country]);
    }

    public function store(CreateCountryRequest $request): JsonResponse
    {
        // 1. Validation is handled by FormRequest
        // 2. Map valid data to DTO
        $dto = CreateCountryDTO::fromRequest($request);
        
        // 3. Pass to Service Layer
        $country = $this->countryService->create($dto);
        
        return response()->json(['data' => $country, 'message' => 'Country created successfully.'], 201);
    }

    public function update(UpdateCountryRequest $request, string $id): JsonResponse
    {
        $dto = UpdateCountryDTO::fromRequest($request);
        $country = $this->countryService->update($id, $dto);
        
        return response()->json(['data' => $country, 'message' => 'Country updated successfully.']);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->countryService->delete($id);
        return response()->json(['message' => 'Country deleted successfully.']);
    }
}