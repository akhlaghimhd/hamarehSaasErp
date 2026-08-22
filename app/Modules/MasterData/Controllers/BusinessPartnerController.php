<?php

namespace App\Modules\MasterData\Controllers;

use App\Base\Controller;
use App\Modules\MasterData\Requests\CreateBusinessPartnerRequest;
use App\Modules\MasterData\Requests\UpdateBusinessPartnerRequest;
use App\Modules\MasterData\DTOs\CreateBusinessPartnerDTO;
use App\Modules\MasterData\DTOs\UpdateBusinessPartnerDTO;
use App\Modules\MasterData\Services\BusinessPartnerService;
use Illuminate\Http\JsonResponse;
use Exception;

class BusinessPartnerController extends Controller
{
    public function __construct(
        private readonly BusinessPartnerService $businessPartnerService
    ) {}

    public function index(): JsonResponse
    {
        $partners = $this->businessPartnerService->getAllBusinessPartners();
        
        return response()->json([
            'success' => true,
            'data' => $partners
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $partner = $this->businessPartnerService->getBusinessPartnerById($id);
        
        return response()->json([
            'success' => true,
            'data' => $partner
        ], 200);
    }

    public function store(CreateBusinessPartnerRequest $request): JsonResponse
    {
        try {
            $dto = CreateBusinessPartnerDTO::fromRequest($request->validated());
            $businessPartner = $this->businessPartnerService->createBusinessPartner($dto);

            return response()->json([
                'success' => true,
                'message' => 'Business partner created successfully.',
                'data' => $businessPartner
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the business partner.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(UpdateBusinessPartnerRequest $request, string $id): JsonResponse
    {
        try {
            $dto = UpdateBusinessPartnerDTO::fromRequest($request->validated());
            $businessPartner = $this->businessPartnerService->updateBusinessPartner($id, $dto);

            return response()->json([
                'success' => true,
                'message' => 'Business partner updated successfully.',
                'data' => $businessPartner
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the business partner.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->businessPartnerService->deleteBusinessPartner($id);

            return response()->json([
                'success' => true,
                'message' => 'Business partner deleted successfully.'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the business partner.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}