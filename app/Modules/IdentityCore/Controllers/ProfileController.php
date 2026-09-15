<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Base\Controller;
use App\Modules\IdentityCore\Requests\UpsertUserProfileRequest;
use App\Modules\IdentityCore\Requests\SelfUpsertProfileRequest;
use App\Modules\IdentityCore\Requests\UploadAvatarRequest;
use App\Modules\IdentityCore\DTOs\UpsertUserProfileDTO;
use App\Modules\IdentityCore\DTOs\SelfUpsertUserProfileDTO;
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
                'data'    => $this->presentProfile($profile),
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
     * Self-service upsert: display_bio + address change request only.
     */
    public function upsertMe(SelfUpsertProfileRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()->user_id;
            $dto = SelfUpsertUserProfileDTO::fromRequest($userId, $request->validated());
            $profile = $this->profileService->upsertSelf($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Profile saved successfully.',
                'data'    => $this->presentProfile($profile),
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

    /**
     * Upload / replace avatar (single image).
     */
    public function uploadAvatarMe(UploadAvatarRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()->user_id;
            $profile = $this->profileService->uploadAvatar($userId, $request->file('avatar'));

            return response()->json([
                'status'  => 'success',
                'message' => 'Avatar updated successfully.',
                'data'    => $this->presentProfile($profile),
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
                'data'    => $this->presentProfile($profile),
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
                'data'    => $this->presentProfile($profile),
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

    /**
     * Admin: approve pending address change.
     */
    public function approveAddress(string $userId): JsonResponse
    {
        try {
            $profile = $this->profileService->approveAddressChange($userId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Address change approved.',
                'data'    => $this->presentProfile($profile),
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

    private function presentProfile($profile): array
    {
        return [
            'profile_id'            => $profile->profile_id,
            'user_id'               => $profile->user_id,
            'national_id'           => $profile->national_id,
            'birth_date'            => optional($profile->birth_date)?->format('Y-m-d'),
            'avatar_url'            => $profile->avatar_url,
            'gender'                => $profile->gender,
            'address'               => $profile->address,
            'pending_address'       => $profile->pending_address,
            'address_change_status' => (int) ($profile->address_change_status ?? 0),
            'phone'                 => $profile->phone,
            'display_bio'           => $profile->description,
            'description'           => $profile->description,
            'row_version'           => $profile->row_version,
            'created_at'            => optional($profile->created_at)?->toIso8601String(),
            'updated_at'            => optional($profile->updated_at)?->toIso8601String(),
        ];
    }
}
