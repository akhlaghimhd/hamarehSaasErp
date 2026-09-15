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
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Exception;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService
    ) {}

    public function me(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->user_id;
            $profile = $this->profileService->getByUserId($userId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Profile retrieved successfully.',
                'data'    => $this->presentProfile($profile, $request),
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

    public function upsertMe(SelfUpsertProfileRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()->user_id;
            $dto = SelfUpsertUserProfileDTO::fromRequest($userId, $request->validated());
            $profile = $this->profileService->upsertSelf($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'Profile saved successfully.',
                'data'    => $this->presentProfile($profile, $request),
            ], 200);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
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

    public function uploadAvatarMe(UploadAvatarRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()->user_id;
            $profile = $this->profileService->uploadAvatar($userId, $request->file('avatar'));

            return response()->json([
                'status'  => 'success',
                'message' => 'Avatar updated successfully.',
                'data'    => $this->presentProfile($profile, $request),
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
     * Stream current user's avatar (auth + tenant required).
     */
    public function streamAvatarMe(Request $request): BinaryFileResponse|JsonResponse|Response
    {
        try {
            $userId = $request->user()->user_id;
            $path = $this->profileService->resolveAvatarAbsolutePath($userId);

            if (!$path || !is_file($path)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Avatar not found.',
                ], 404);
            }

            $mime = mime_content_type($path) ?: 'image/jpeg';

            return response()->file($path, [
                'Content-Type'  => $mime,
                'Cache-Control' => 'private, max-age=300',
            ]);
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

    public function requestMobileChange(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'mobile' => ['required', 'string', 'max:20'],
            ]);

            $data = $this->profileService->requestMobileChange(
                $request->user()->user_id,
                $request->input('mobile'),
                $request->ip()
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'OTP sent.',
                'data'    => $data,
            ], 200);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User not found.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function verifyMobileChange(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'mobile' => ['required', 'string', 'max:20'],
                'code'   => ['required', 'string', 'max:10'],
            ]);

            $user = $this->profileService->verifyMobileChange(
                $request->user()->user_id,
                $request->input('mobile'),
                $request->input('code')
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Mobile updated.',
                'data'    => [
                    'user_id'    => $user->user_id,
                    'mobile'     => $user->mobile,
                    'email'      => $user->email,
                    'first_name' => $user->first_name,
                    'last_name'  => $user->last_name,
                ],
            ], 200);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
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

    private function presentProfile($profile, ?Request $request = null): array
    {
        $user = $request?->user();
        $hasAvatar = !empty($profile->avatar_url);

        return [
            'profile_id'            => $profile->profile_id,
            'user_id'               => $profile->user_id,
            'national_id'           => $profile->national_id,
            'birth_date'            => optional($profile->birth_date)?->format('Y-m-d'),
            'avatar_url'            => $profile->avatar_url,
            'has_avatar'            => $hasAvatar,
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
            'user' => $user ? [
                'user_id'    => $user->user_id,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => $user->email,
                'mobile'     => $user->mobile,
            ] : null,
        ];
    }
}
