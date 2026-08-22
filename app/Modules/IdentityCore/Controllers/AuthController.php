<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Base\Controller;
use App\Base\Context\TenantContext;
use App\Modules\IdentityCore\DTOs\LoginDTO;
use App\Modules\IdentityCore\DTOs\UserRegistrationDTO;
use App\Modules\IdentityCore\Services\AuthenticationService;
use App\Modules\IdentityCore\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthController extends Controller
{
    private AuthenticationService $authService;

    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request): JsonResponse
    {
        try {
            $dto = LoginDTO::fromRequest($request);
            $result = $this->authService->login($dto);

            return response()->json([
                'status'  => 'success',
                'message' => 'ورود با موفقیت انجام شد.',
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

            // Associate user with tenant
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