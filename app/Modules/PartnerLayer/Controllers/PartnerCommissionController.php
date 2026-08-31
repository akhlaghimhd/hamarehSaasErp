<?php

namespace App\Modules\PartnerLayer\Controllers;

use App\Base\Controller;
use App\Modules\PartnerLayer\Requests\CreatePartnerCommissionRequest;
use App\Modules\PartnerLayer\Requests\UpdatePartnerCommissionRequest;
use App\Modules\PartnerLayer\DTOs\CreatePartnerCommissionDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerCommissionDTO;
use App\Modules\PartnerLayer\Services\PartnerCommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class PartnerCommissionController extends Controller
{
    public function __construct(
        private readonly PartnerCommissionService $commissionService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->query('partner_id');

        $items = $this->commissionService->getCommissions(
            $partnerId ? (string) $partnerId : null
        );

        return response()->json([
            'status' => 'success',
            'data'   => $items,
        ]);
    }

    public function store(CreatePartnerCommissionRequest $request): JsonResponse
    {
        try {
            $dto = CreatePartnerCommissionDTO::fromRequest($request->validated());
            $item = $this->commissionService->createCommission($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Partner commission created successfully.',
                'data'    => $item,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(string $commission): JsonResponse
    {
        try {
            $item = $this->commissionService->getCommissionById($commission);

            return response()->json([
                'status' => 'success',
                'data'   => $item,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Commission not found or access denied.',
            ], 404);
        }
    }

    public function update(string $commission, UpdatePartnerCommissionRequest $request): JsonResponse
    {
        try {
            $dto = UpdatePartnerCommissionDTO::fromRequest($request->validated());
            $item = $this->commissionService->updateCommission($commission, $dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Commission updated successfully.',
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

    public function destroy(string $commission): JsonResponse
    {
        try {
            $this->commissionService->deleteCommission($commission);

            return response()->json([
                'status'  => 'success',
                'message' => 'Commission deleted successfully.',
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
