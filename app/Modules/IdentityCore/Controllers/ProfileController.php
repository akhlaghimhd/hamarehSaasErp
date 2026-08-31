<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Base\Controller;
use App\Modules\IdentityCore\Requests\UpsertUserProfileRequest;
use App\Modules\IdentityCore\DTOs\UpsertUserProfileDTO;
use App\Modules\IdentityCore\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService
    ) {}

    /**
     * Current authenticated user's profile (self-service).
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->user_id;
            $profile = $this->profileService->getByUserId($userId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Profile retrieved successfully.',
                'data'    => $profile,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Profile not found.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Upsert current authenticated user's profile (self-service).
     */
    public function upsertMe(UpsertUserProfileRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()->user_id;
            $dto = UpsertUserProfileDTO::fromRequest($userId, $request->validated());
            $profile = $this->profileService->upsert($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Profile saved successfully.',
                'data'    => $profile,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User or membership not found.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function show(string $userId): JsonResponse
    {
        try {
            $profile = $this->profileService->getByUserId($userId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Profile retrieved successfully.',
                'data'    => $profile,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Profile not found.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function upsert(UpsertUserProfileRequest $request, string $userId): JsonResponse
    {
        try {
            $dto = UpsertUserProfileDTO::fromRequest($userId, $request->validated());
            $profile = $this->profileService->upsert($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Profile saved successfully.',
                'data'    => $profile,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User or membership not found.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function destroy(string $userId): JsonResponse
    {
        try {
            $this->profileService->softDelete($userId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Profile soft-deleted successfully.',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Profile not found.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
