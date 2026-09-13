<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Base\Controller;
use App\Base\Context\TenantContext;
use App\Modules\IdentityCore\DTOs\LoginDTO;
use App\Modules\IdentityCore\DTOs\UserRegistrationDTO;
use App\Modules\IdentityCore\Services\AuthenticationService;
use App\Modules\IdentityCore\Services\OtpLoginService;
use App\Modules\IdentityCore\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthenticationService $authService,
        private readonly OtpLoginService $otpLoginService,
    ) {
    }

    public function login(Request $request): JsonResponse
    {
        try {
            $dto = LoginDTO::fromRequest($request);
            $result = $this->authService->login($dto);

            return response()->json([
                'status'  => 'success',
                'message' => ($result['requires_tenant_selection'] ?? false)
                    ? 'انتخاب سازمان الزامی است.'
                    : 'ورود با موفقیت انجام شد.',
                'data'    => $result,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'خطای اعتبارسنجی',
                'errors'  => $e->errors(),
            ], 422);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function selectTenant(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'tenant_id' => 'required|string',
            ]);

            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Unauthorized',
                ], 401);
            }

            $result = $this->authService->selectTenant($user, $request->input('tenant_id'));

            return response()->json([
                'status'  => 'success',
                'message' => 'ورود با موفقیت انجام شد.',
                'data'    => $result,
            ], 200);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function requestOtp(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'mobile' => 'required|string|max:20',
            ]);

            $result = $this->otpLoginService->requestOtp(
                $request->input('mobile'),
                $request->ip()
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'در صورت صحت شماره، کد تأیید ارسال شد.',
                'data'    => $result,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'خطای اعتبارسنجی',
                'errors'  => $e->errors(),
            ], 422);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'mobile'    => 'required|string|max:20',
                'code'      => 'required|string|min:4|max:10',
                'tenant_id' => 'nullable|string',
            ]);

            $result = $this->otpLoginService->verifyOtp(
                $request->input('mobile'),
                $request->input('code'),
                $request->input('tenant_id')
            );

            return response()->json([
                'status'  => 'success',
                'message' => ($result['requires_tenant_selection'] ?? false)
                    ? 'انتخاب سازمان الزامی است.'
                    : 'ورود با موفقیت انجام شد.',
                'data'    => $result,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'خطای اعتبارسنجی',
                'errors'  => $e->errors(),
            ], 422);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $tenantId = TenantContext::getInstance()->getTenantId();

            if (!$user || !$tenantId) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Unauthorized or missing tenant context.',
                ], 401);
            }

            $tenantUserId = Context::get('tenant_user_id');

            $this->authService->logout($user, $tenantId, $tenantUserId);

            return response()->json([
                'status'  => 'success',
                'message' => 'خروج با موفقیت انجام شد.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function register(Request $request): JsonResponse
    {
        try {
            $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

            if (!$tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant context is required for registration.',
                ], 400);
            }

            $dto = UserRegistrationDTO::fromRequest($request->all());
            $user = $this->authService->register($dto);

            TenantUser::create([
                'tenant_id'  => $tenantId,
                'user_id'    => $user->user_id,
                'status'     => 1,
                'is_owner'   => $request->input('is_owner', false) ? 1 : 0,
                'created_by' => $user->user_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully.',
                'data' => [
                    'first_name' => $user->first_name,
                    'last_name'  => $user->last_name,
                    'email'      => $user->email,
                    'user_id'    => $user->user_id,
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
