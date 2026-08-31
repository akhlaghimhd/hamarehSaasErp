<?php

namespace App\Modules\PartnerLayer\Controllers;

use App\Base\Controller;
use App\Modules\PartnerLayer\Requests\CreatePartnerAgreementRequest;
use App\Modules\PartnerLayer\Requests\UpdatePartnerAgreementRequest;
use App\Modules\PartnerLayer\DTOs\CreatePartnerAgreementDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerAgreementDTO;
use App\Modules\PartnerLayer\Services\PartnerAgreementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class PartnerAgreementController extends Controller
{
    public function __construct(
        private readonly PartnerAgreementService $agreementService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $partnerId = $request->query('partner_id');

            $items = $this->agreementService->getAgreements(
                $partnerId ? (string) $partnerId : null
            );

            return response()->json([
                'status' => 'success',
                'data'   => $items,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function store(CreatePartnerAgreementRequest $request): JsonResponse
    {
        try {
            $dto = CreatePartnerAgreementDTO::fromRequest($request->validated());
            $item = $this->agreementService->createAgreement($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Partner agreement created successfully.',
                'data'    => $item,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(string $agreement): JsonResponse
    {
        try {
            $item = $this->agreementService->getAgreementById($agreement);

            return response()->json([
                'status' => 'success',
                'data'   => $item,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Agreement not found or access denied.',
            ], 404);
        }
    }

    public function update(string $agreement, UpdatePartnerAgreementRequest $request): JsonResponse
    {
        try {
            $dto = UpdatePartnerAgreementDTO::fromRequest($request->validated());
            $item = $this->agreementService->updateAgreement($agreement, $dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Agreement updated successfully.',
                'data'    => $item,
            ]);
        } catch (Exception $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 422;

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function destroy(string $agreement): JsonResponse
    {
        try {
            $this->agreementService->deleteAgreement($agreement);

            return response()->json([
                'status'  => 'success',
                'message' => 'Agreement deleted successfully.',
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
