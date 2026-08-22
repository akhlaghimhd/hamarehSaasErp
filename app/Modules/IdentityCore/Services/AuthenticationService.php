<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\DTOs\LoginDTO;
use App\Modules\IdentityCore\DTOs\UserRegistrationDTO;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\UserCredential;
use App\Modules\IdentityCore\Models\TenantUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthenticationService
{
    /**
     * @throws ValidationException
     * @throws HttpException
     * @throws Exception
     */
    public function login(LoginDTO $dto): array
    {
        // 1. Find the user by email
        $user = User::where('email', $dto->email)->first();

        if (!$user) {
            throw new HttpException(401, 'ایمیل یا رمز عبور اشتباه است.');
        }

        // 2. Load credential and verify password
        $credential = $user->credential;

        if (!$credential || !Hash::check($dto->password, $credential->password_hash)) {
            throw new HttpException(401, 'ایمیل یا رمز عبور اشتباه است.');
        }

        if ($user->status !== 1) {
            throw new HttpException(403, 'حساب کاربری شما غیرفعال یا مسدود شده است.');
        }

        // 3. Resolve and validate Tenant membership
        $tenantIdToLogin = $dto->tenantId;

        if ($tenantIdToLogin) {
            $tenantUser = TenantUser::withoutGlobalScopes()
                ->where('tenant_id', $tenantIdToLogin)
                ->where('user_id', $user->user_id)
                ->whereNull('deleted_at')
                ->first();

            if (!$tenantUser) {
                throw new HttpException(401, 'ایمیل یا رمز عبور اشتباه است.');
            }

            // status: 1 = Active, 2 = Suspended
            if ((int) $tenantUser->status === 2) {
                throw new HttpException(403, 'Your account is suspended in this organization.');
            }

            if ((int) $tenantUser->status !== 1) {
                throw new HttpException(403, 'Your account is not active in this organization.');
            }
        }

        // 4. Issue Token (Laravel Sanctum)
        $tokenAbilities = ['*'];
        $tokenName = 'auth_token';

        if ($tenantIdToLogin) {
            $tokenName .= '_tenant_' . $tenantIdToLogin;
            $tokenAbilities[] = 'tenant:' . $tenantIdToLogin;
        }

        $tokenResult = $user->createToken($tokenName, $tokenAbilities);

        // 5. Update Last Login (Audit)
        $user->last_login_at = now();
        $user->save();

        return [
            'access_token' => $tokenResult->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_in'   => null,
            'user' => [
                'user_id'        => $user->user_id,
                'tenant_user_id' => isset($tenantUser) ? $tenantUser->tenant_user_id : null,
                'first_name'     => $user->first_name,
                'last_name'      => $user->last_name,
                'email'          => $user->email,
            ],
            'active_tenant_id' => $tenantIdToLogin,
        ];
    }

    /**
     * Register a new user
     */
    public function register(UserRegistrationDTO $dto): User
    {
        $user = User::create([
            'first_name' => $dto->firstName,
            'last_name'  => $dto->lastName,
            'mobile'     => $dto->mobile,
            'email'      => $dto->email,
            'user_kind'  => $dto->userKind,
            'status'     => $dto->status,
        ]);

        UserCredential::create([
            'user_id'             => $user->user_id,
            'password_hash'       => Hash::make($dto->password),
            'authentication_type' => 1,
            'is_verified'         => false,
            'two_factor_enabled'  => false,
        ]);

        return $user;
    }
}