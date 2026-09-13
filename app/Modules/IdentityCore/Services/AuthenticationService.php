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
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthenticationService
{
    /**
     * Password login with identifier (email or mobile).
     * Tenant is resolved by the system — never required as user-typed input.
     * Optional tenant_id only after organization picker (system-driven).
     */
    public function login(LoginDTO $dto): array
    {
        $user = $this->findUserByIdentifier($dto->identifier);

        if (!$user) {
            throw new HttpException(401, 'اطلاعات ورود نادرست است.');
        }

        $credential = $user->credential;

        if ($credential && $credential->locked_until && $credential->locked_until->isFuture()) {
            throw new HttpException(403, 'حساب کاربری موقتاً قفل شده است. لطفاً بعداً تلاش کنید.');
        }

        if (!$credential || !Hash::check($dto->password, $credential->password_hash)) {
            if ($credential) {
                $this->registerFailedLoginAttempt($credential);
            }
            throw new HttpException(401, 'اطلاعات ورود نادرست است.');
        }

        if ((int) $user->status !== 1) {
            throw new HttpException(403, 'حساب کاربری شما غیرفعال یا مسدود شده است.');
        }

        $this->clearFailedLoginAttempts($credential);

        return $this->completeLoginForUser($user, $dto->tenantId);
    }

    /**
     * Shared completion for password and OTP login.
     *
     * @return array login payload OR requires_tenant_selection payload
     */
    public function completeLoginForUser(User $user, ?string $requestedTenantId = null): array
    {
        $memberships = TenantUser::withoutGlobalScopes()
            ->where('user_id', $user->user_id)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->get();

        if ($memberships->isEmpty()) {
            throw new HttpException(403, 'عضویت فعالی در هیچ سازمانی برای این کاربر یافت نشد.');
        }

        $tenantIdToLogin = $requestedTenantId;

        if ($tenantIdToLogin) {
            $allowed = $memberships->firstWhere('tenant_id', $tenantIdToLogin);
            if (!$allowed) {
                throw new HttpException(401, 'اطلاعات ورود نادرست است.');
            }
        } elseif ($memberships->count() === 1) {
            $tenantIdToLogin = $memberships->first()->tenant_id;
        } else {
            // Multiple orgs: UI shows names only; system keeps tenant_id internal
            $tenantIds = $memberships->pluck('tenant_id')->all();
            $tenants = Tenant::query()
                ->whereIn('tenant_id', $tenantIds)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->get(['tenant_id', 'tenant_code', 'tenant_name', 'slug']);

            $preAuth = $user->createToken('pre_auth_select_tenant', ['pre_auth'])->plainTextToken;

            return [
                'requires_tenant_selection' => true,
                'pre_auth_token'            => $preAuth,
                'token_type'                => 'Bearer',
                'user' => [
                    'user_id'    => $user->user_id,
                    'first_name' => $user->first_name,
                    'last_name'  => $user->last_name,
                    'email'      => $user->email,
                    'mobile'     => $user->mobile,
                ],
                'organizations' => $tenants->map(fn ($t) => [
                    'tenant_id'   => $t->tenant_id,
                    'tenant_code' => $t->tenant_code,
                    'tenant_name' => $t->tenant_name,
                    'slug'        => $t->slug,
                ])->values()->all(),
            ];
        }

        return $this->issueTenantSession($user, $tenantIdToLogin);
    }

    /**
     * After multi-org selection (authenticated with pre_auth token).
     */
    public function selectTenant(User $user, string $tenantId): array
    {
        return $this->completeLoginForUser($user, $tenantId);
    }

    private function issueTenantSession(User $user, string $tenantIdToLogin): array
    {
        $tenantUser = TenantUser::withoutGlobalScopes()
            ->where('tenant_id', $tenantIdToLogin)
            ->where('user_id', $user->user_id)
            ->whereNull('deleted_at')
            ->first();

        if (!$tenantUser) {
            throw new HttpException(401, 'اطلاعات ورود نادرست است.');
        }

        if ((int) $tenantUser->status === 2) {
            throw new HttpException(403, 'Your account is suspended in this organization.');
        }

        if ((int) $tenantUser->status !== 1) {
            throw new HttpException(403, 'Your account is not active in this organization.');
        }

        $roles = [];
        $permissions = [];
        $scopes = [];

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
                ->get(['tenant_role_id', 'code', 'name', 'is_system_default']);

            $roles = $roleModels->map(function ($role) {
                return [
                    'role_id'           => $role->tenant_role_id,
                    'code'              => $role->code,
                    'name'              => $role->name,
                    'is_system_default' => (bool) $role->is_system_default,
                ];
            })->values()->toArray();

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

        // Revoke previous pre_auth tokens for this user
        $user->tokens()->where('name', 'pre_auth_select_tenant')->delete();

        $tokenName = 'auth_token_tenant_'.$tenantIdToLogin;
        $tokenResult = $user->createToken($tokenName, ['*', 'tenant:'.$tenantIdToLogin]);

        $user->last_login_at = now();
        $user->save();

        $securityContext = [
            'user_id'        => $user->user_id,
            'tenant_id'      => $tenantIdToLogin,
            'tenant_user_id' => $tenantUser->tenant_user_id,
            'roles'          => $roles,
            'permissions'    => $permissions,
            'scopes'         => $scopes,
            'is_owner'       => (bool) $tenantUser->is_owner,
        ];

        $this->writeOutboxEvent(
            tenantId: $tenantIdToLogin,
            aggregateId: $user->user_id,
            eventType: 'identity.user.logged_in.v1',
            payload: [
                'user_id'        => $user->user_id,
                'tenant_id'      => $tenantIdToLogin,
                'tenant_user_id' => $tenantUser->tenant_user_id,
                'token_name'     => $tokenName,
                'occurred_at'    => now()->toIso8601String(),
            ]
        );

        return [
            'requires_tenant_selection' => false,
            'access_token'              => $tokenResult->plainTextToken,
            'token_type'                => 'Bearer',
            'expires_in'                => null,
            'user' => [
                'user_id'        => $user->user_id,
                'tenant_user_id' => $tenantUser->tenant_user_id,
                'first_name'     => $user->first_name,
                'last_name'      => $user->last_name,
                'email'          => $user->email,
                'mobile'         => $user->mobile,
            ],
            'active_tenant_id' => $tenantIdToLogin,
            'organization' => [
                'tenant_id'   => $tenantIdToLogin,
                'tenant_name' => Tenant::query()->where('tenant_id', $tenantIdToLogin)->value('tenant_name'),
                'tenant_code' => Tenant::query()->where('tenant_id', $tenantIdToLogin)->value('tenant_code'),
            ],
            'security_context' => $securityContext,
        ];
    }

    public function findUserByIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);

        $query = User::query()->whereNull('deleted_at');

        if (str_contains($identifier, '@')) {
            return $query->where('email', $identifier)->first();
        }

        $mobile = preg_replace('/\D+/', '', $identifier) ?? $identifier;
        if (str_starts_with($mobile, '98') && strlen($mobile) === 12) {
            $mobile = '0'.substr($mobile, 2);
        }
        if (str_starts_with($mobile, '9') && strlen($mobile) === 10) {
            $mobile = '0'.$mobile;
        }

        return $query->where('mobile', $mobile)->first();
    }

    public function logout(User $user, string $tenantId, ?string $tenantUserId = null): void
    {
        $tokenName = null;
        $current = $user->currentAccessToken();

        if ($current instanceof PersonalAccessToken) {
            $tokenName = $current->name;
            $current->delete();
        } else {
            $tokenName = $user->tokens()->value('name');
            $user->tokens()->delete();
        }

        $this->writeOutboxEvent(
            tenantId: $tenantId,
            aggregateId: $user->user_id,
            eventType: 'identity.user.logged_out.v1',
            payload: [
                'user_id'        => $user->user_id,
                'tenant_id'      => $tenantId,
                'tenant_user_id' => $tenantUserId,
                'token_name'     => $tokenName,
                'occurred_at'    => now()->toIso8601String(),
            ]
        );
    }

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

    private function registerFailedLoginAttempt(UserCredential $credential): void
    {
        $maxAttempts = 5;
        $lockMinutes = 15;
        $count = (int) $credential->failed_login_count + 1;
        $credential->failed_login_count = $count;
        if ($count >= $maxAttempts) {
            $credential->locked_until = now()->addMinutes($lockMinutes);
            $credential->failed_login_count = 0;
        }
        $credential->save();
    }

    private function clearFailedLoginAttempts(UserCredential $credential): void
    {
        if ((int) $credential->failed_login_count !== 0 || $credential->locked_until !== null) {
            $credential->failed_login_count = 0;
            $credential->locked_until = null;
            $credential->save();
        }
    }

    private function writeOutboxEvent(
        string $tenantId,
        string $aggregateId,
        string $eventType,
        array $payload
    ): void {
        DB::table('event_outbox')->insert([
            'event_id'       => (string) Str::uuid(),
            'tenant_id'      => $tenantId,
            'aggregate_type' => 'users',
            'aggregate_id'   => $aggregateId,
            'event_type'     => $eventType,
            'payload'        => json_encode($payload),
            'status'         => 1,
            'retry_count'    => 0,
            'created_at'     => now(),
        ]);
    }
}
