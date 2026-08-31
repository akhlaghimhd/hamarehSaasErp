<?php

namespace App\Modules\PartnerLayer\Controllers;

use App\Base\Controller;
use App\Modules\PartnerLayer\Requests\CreatePartnerPayoutRequest;
use App\Modules\PartnerLayer\Requests\UpdatePartnerPayoutRequest;
use App\Modules\PartnerLayer\DTOs\CreatePartnerPayoutDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerPayoutDTO;
use App\Modules\PartnerLayer\Services\PartnerPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class PartnerPayoutController extends Controller
{
    public function __construct(
        private readonly PartnerPayoutService $payoutService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->query('partner_id');

        $items = $this->payoutService->getPayouts(
            $partnerId ? (string) $partnerId : null
        );

        return response()->json([
            'status' => 'success',
            'data'   => $items,
        ]);
    }

    public function store(CreatePartnerPayoutRequest $request): JsonResponse
    {
        try {
            $dto = CreatePartnerPayoutDTO::fromRequest($request->validated());
            $item = $this->payoutService->createPayout($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Partner payout created successfully.',
                'data'    => $item,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(string $payout): JsonResponse
    {
        try {
            $item = $this->payoutService->getPayoutById($payout);

            return response()->json([
                'status' => 'success',
                'data'   => $item,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Payout not found or access denied.',
            ], 404);
        }
    }

    public function update(string $payout, UpdatePartnerPayoutRequest $request): JsonResponse
    {
        try {
            $dto = UpdatePartnerPayoutDTO::fromRequest($request->validated());
            $item = $this->payoutService->updatePayout($payout, $dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Payout updated successfully.',
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

    public function destroy(string $payout): JsonResponse
    {
        try {
            $this->payoutService->deletePayout($payout);

            return response()->json([
                'status'  => 'success',
                'message' => 'Payout deleted successfully.',
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
