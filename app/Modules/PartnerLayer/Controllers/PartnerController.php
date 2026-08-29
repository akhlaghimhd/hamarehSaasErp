<?php

namespace App\Modules\PartnerLayer\Controllers;

use App\Base\Controller;
use App\Modules\PartnerLayer\Requests\CreatePartnerRequest;
use App\Modules\PartnerLayer\Requests\UpdatePartnerRequest;
use App\Modules\PartnerLayer\DTOs\CreatePartnerDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerDTO;
use App\Modules\PartnerLayer\Services\PartnerService;
use Illuminate\Http\JsonResponse;
use Exception;

class PartnerController extends Controller
{
    public function __construct(
        private readonly PartnerService $partnerService
    ) {
    }

    public function index(): JsonResponse
    {
        $partners = $this->partnerService->getAllPartners();

        return response()->json([
            'status' => 'success',
            'data'   => $partners,
        ]);
    }

    public function store(CreatePartnerRequest $request): JsonResponse
    {
        try {
            $dto = CreatePartnerDTO::fromRequest($request->validated());
            $partner = $this->partnerService->createPartner($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Partner created successfully.',
                'data'    => $partner,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(string $partner): JsonResponse
    {
        try {
            $model = $this->partnerService->getPartnerById($partner);

            return response()->json([
                'status' => 'success',
                'data'   => $model,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Partner not found or access denied.',
            ], 404);
        }
    }

    public function update(string $partner, UpdatePartnerRequest $request): JsonResponse
    {
        try {
            $dto = UpdatePartnerDTO::fromRequest($request->validated());
            $model = $this->partnerService->updatePartner($partner, $dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Partner updated successfully.',
                'data'    => $model,
            ]);
        } catch (Exception $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 422;

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function destroy(string $partner): JsonResponse
    {
        try {
            $this->partnerService->deletePartner($partner);

            return response()->json([
                'status'  => 'success',
                'message' => 'Partner deleted successfully.',
            ]);
        } catch (Exception $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 422;

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}
