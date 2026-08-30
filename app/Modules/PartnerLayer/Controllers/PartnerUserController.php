<?php

namespace App\Modules\PartnerLayer\Controllers;

use App\Base\Controller;
use App\Modules\PartnerLayer\Requests\CreatePartnerUserRequest;
use App\Modules\PartnerLayer\Requests\UpdatePartnerUserRequest;
use App\Modules\PartnerLayer\DTOs\CreatePartnerUserDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerUserDTO;
use App\Modules\PartnerLayer\Services\PartnerUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class PartnerUserController extends Controller
{
    public function __construct(
        private readonly PartnerUserService $partnerUserService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->query('partner_id');

        $items = $this->partnerUserService->getPartnerUsers(
            $partnerId ? (string) $partnerId : null
        );

        return response()->json([
            'status' => 'success',
            'data'   => $items,
        ]);
    }

    public function store(CreatePartnerUserRequest $request): JsonResponse
    {
        try {
            $dto = CreatePartnerUserDTO::fromRequest($request->validated());
            $item = $this->partnerUserService->createPartnerUser($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Partner user linked successfully.',
                'data'    => $item,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(string $partnerUser): JsonResponse
    {
        try {
            $item = $this->partnerUserService->getPartnerUserById($partnerUser);

            return response()->json([
                'status' => 'success',
                'data'   => $item,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Partner user not found or access denied.',
            ], 404);
        }
    }

    public function update(string $partnerUser, UpdatePartnerUserRequest $request): JsonResponse
    {
        try {
            $dto = UpdatePartnerUserDTO::fromRequest($request->validated());
            $item = $this->partnerUserService->updatePartnerUser($partnerUser, $dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Partner user updated successfully.',
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

    public function destroy(string $partnerUser): JsonResponse
    {
        try {
            $this->partnerUserService->deletePartnerUser($partnerUser);

            return response()->json([
                'status'  => 'success',
                'message' => 'Partner user unlinked successfully.',
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
