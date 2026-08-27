<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\DTOs\LoginDTO;
use App\Modules\IdentityCore\DTOs\UserRegistrationDTO;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\UserCredential;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantUserScope;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthenticationService
{
    /**
     * Login and return full security context according to Architecture Law 4.4
     * Required context: user_id, tenant_id, roles, scopes
     *
     * @throws ValidationException
     * @throws HttpException
     * @throws Exception
     */
    public function login(LoginDTO $dto): array
    {
        // 1. Find the user by email
        $user = User::where('email', $dto->email)
            ->whereNull('deleted_at')
            ->first();

        if (!$user) {
            throw new HttpException(401, 'ایمیل یا رمز عبور اشتباه است.');
        }

        // 2. Load credential and verify password
        $credential = $user->credential;

        if (!$credential || !Hash::check($dto->password, $credential->password_hash)) {
            // Optional: increment failed_login_count here if desired
            throw new HttpException(401, 'ایمیل یا رمز عبور اشتباه است.');
        }

        if ((int) $user->status !== 1) {
            throw new HttpException(403, 'حساب کاربری شما غیرفعال یا مسدود شده است.');
        }

        // 3. Resolve and validate Tenant membership
        $tenantIdToLogin = $dto->tenantId;
        $tenantUser = null;
        $roles = [];
        $permissions = [];
        $scopes = [];

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

            // 3.1 Load Roles for this tenant membership
            $roleIds = TenantUserRole::withoutGlobalScopes()
                ->where('tenant_id', $tenantIdToLogin)
                ->where('user_id', $user->user_id)
                ->whereNull('deleted_at')
                ->pluck('tenant_role_id')
                ->unique()
                ->values()
                ->toArray();

            if (!empty($roleIds)) {
                $roleModels = TenantRole::withoutGlobalScopes()
                    ->where('tenant_id', $tenantIdToLogin)
                    ->whereIn('tenant_role_id', $roleIds)
                    ->where('status', 1)
                    ->whereNull('deleted_at')
                    ->get(['tenant_role_id', 'code', 'name', 'role_type']);

                $roles = $roleModels->map(function ($role) {
                    return [
                        'role_id'   => $role->tenant_role_id,
                        'code'      => $role->code,
                        'name'      => $role->name,
                        'role_type' => $role->role_type,
                    ];
                })->values()->toArray();

                // 3.2 Load Permissions via roles
                $permissionIds = TenantRolePermission::withoutGlobalScopes()
                    ->where('tenant_id', $tenantIdToLogin)
                    ->whereIn('tenant_role_id', $roleIds)
                    ->whereNull('deleted_at')
                    ->pluck('tenant_permission_id')
                    ->unique()
                    ->values()
                    ->toArray();

                if (!empty($permissionIds)) {
                    $permissions = TenantPermission::withoutGlobalScopes()
                        ->where('tenant_id', $tenantIdToLogin)
                        ->whereIn('tenant_permission_id', $permissionIds)
                        ->where('status', 1)
                        ->whereNull('deleted_at')
                        ->pluck('code')
                        ->unique()
                        ->values()
                        ->toArray();
                }
            }

            // 3.3 Load Scopes assigned to this tenant_user
            $scopeAssignments = TenantUserScope::withoutGlobalScopes()
                ->where('tenant_id', $tenantIdToLogin)
                ->where('tenant_user_id', $tenantUser->tenant_user_id)
                ->whereNull('deleted_at')
                ->get(['scope_id']);

            $scopeIds = $scopeAssignments->pluck('scope_id')->unique()->values()->toArray();

            if (!empty($scopeIds)) {
                $scopeModels = TenantScope::withoutGlobalScopes()
                    ->where('tenant_id', $tenantIdToLogin)
                    ->whereIn('scope_id', $scopeIds)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->get(['scope_id', 'scope_name', 'scope_type', 'reference_id']);

                $scopes = $scopeModels->map(function ($scope) {
                    return [
                        'scope_id'     => $scope->scope_id,
                        'scope_name'   => $scope->scope_name,
                        'scope_type'   => $scope->scope_type,
                        'reference_id' => $scope->reference_id,
                    ];
                })->values()->toArray();
            }
        }

        // 4. Issue Token (Laravel Sanctum) with tenant ability
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

        // 6. Build full Security Context according to Law 4.4
        // Required: user_id, tenant_id, roles, scopes
        $securityContext = [
            'user_id'         => $user->user_id,
            'tenant_id'       => $tenantIdToLogin,
            'tenant_user_id'  => $tenantUser?->tenant_user_id,
            'roles'           => $roles,
            'permissions'     => $permissions,
            'scopes'          => $scopes,
            'is_owner'        => $tenantUser ? (bool) $tenantUser->is_owner : false,
        ];

        return [
            'access_token'     => $tokenResult->plainTextToken,
            'token_type'       => 'Bearer',
            'expires_in'       => null,
            'user' => [
                'user_id'        => $user->user_id,
                'tenant_user_id' => $tenantUser?->tenant_user_id,
                'first_name'     => $user->first_name,
                'last_name'      => $user->last_name,
                'email'          => $user->email,
            ],
            'active_tenant_id' => $tenantIdToLogin,
            // Full security context required by Architecture Law 4.4
            'security_context' => $securityContext,
        ];
    }

    /**
     * Register a new user
     */
    public function register(UserRegistrationDTO $dto): User
    {
        return DB::transaction(function () use ($dto) {
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
        });
    }
}