<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Base\Controller;
use App\Base\Context\TenantContext;
use App\Modules\IdentityCore\DTOs\LoginDTO;
use App\Modules\IdentityCore\DTOs\UserRegistrationDTO;
use App\Modules\IdentityCore\Services\AuthenticationService;
use App\Modules\IdentityCore\Services\OtpLoginService;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\User;
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

            $message = ($result['must_set_password'] ?? false)
                ? 'لطفاً رمز عبور خود را تعیین کنید.'
                : (($result['requires_tenant_selection'] ?? false)
                    ? 'انتخاب سازمان الزامی است.'
                    : 'ورود با موفقیت انجام شد.');

            return response()->json([
                'status'  => 'success',
                'message' => $message,
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
                'force_resend' => 'sometimes|boolean',
            ]);

            $result = $this->otpLoginService->requestOtp(
                $request->input('mobile'),
                $request->ip(),
                (bool) $request->boolean('force_resend')
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

            $message = ($result['must_set_password'] ?? false)
                ? 'لطفاً رمز عبور خود را تعیین کنید.'
                : (($result['requires_tenant_selection'] ?? false)
                    ? 'انتخاب سازمان الزامی است.'
                    : 'ورود با موفقیت انجام شد.');

            return response()->json([
                'status'  => 'success',
                'message' => $message,
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

    /**
     * First-login / must_set_password: set password using limited token (ability set-password).
     * After success, all tokens are revoked — user must login again with the new password.
     */
    public function setPassword(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'password' => 'required|string|min:6|max:128',
                'password_confirmation' => 'required|same:password',
            ]);

            /** @var User|null $user */
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Unauthorized',
                ], 401);
            }

            // Prefer ability check; also allow if credential still requires set
            $token = $user->currentAccessToken();
            $hasAbility = $token && method_exists($token, 'can') && $token->can('set-password');
            $credential = $user->credential;
            $mustSet = $credential && (bool) $credential->must_set_password;

            if (!$hasAbility && !$mustSet) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'اجازه تعیین رمز اولیه برای این نشست وجود ندارد.',
                ], 403);
            }

            $this->authService->setPassword($user, $request->input('password'));

            return response()->json([
                'status'  => 'success',
                'message' => 'رمز عبور با موفقیت تعیین شد. لطفاً دوباره وارد شوید.',
                'data'    => ['must_relogin' => true],
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

    /**
     * Profile change-password (full session). Requires current password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'current_password' => 'required|string',
                'password' => 'required|string|min:6|max:128',
                'password_confirmation' => 'required|same:password',
            ]);

            /** @var User|null $user */
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Unauthorized',
                ], 401);
            }

            $this->authService->changePassword(
                $user,
                $request->input('current_password'),
                $request->input('password')
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'رمز عبور با موفقیت تغییر کرد.',
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

    /**
     * Forgot-password step 1: same as OTP request (reuse OtpLoginService).
     * Frontend can call /auth/otp/request; this alias exists for clarity.
     */
    public function forgotPasswordRequest(Request $request): JsonResponse
    {
        return $this->requestOtp($request);
    }

    /**
     * Forgot-password step 2: verify OTP + set new password in one step.
     * Does not issue a session — user must login with the new password.
     */
    public function forgotPasswordConfirm(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'mobile'    => 'required|string|max:20',
                'code'      => 'required|string|min:4|max:10',
                'password'  => 'required|string|min:6|max:128',
                'password_confirmation' => 'required|same:password',
            ]);

            $mobile = $request->input('mobile');
            $code = $request->input('code');

            // Verify OTP (consumes it) but we intercept before full login session
            // by verifying via service then applying password reset.
            // OtpLoginService::verifyOtp issues session — for forgot we need only consume OTP.
            // Re-use verify path carefully: call internal verify that returns user.

            $user = $this->otpLoginService->verifyOtpForPasswordReset(
                $mobile,
                $code
            );

            $this->authService->resetPasswordByUser($user, $request->input('password'));

            return response()->json([
                'status'  => 'success',
                'message' => 'رمز عبور با موفقیت بازنشانی شد. لطفاً با رمز جدید وارد شوید.',
                'data'    => ['must_relogin' => true],
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
