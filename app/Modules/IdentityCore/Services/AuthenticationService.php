<?php

namespace App\Modules\IdentityCore\Services;

use App\Base\Http\Middleware\LoadUserScopesMiddleware;
use App\Modules\IdentityCore\DTOs\LoginDTO;
use App\Modules\IdentityCore\DTOs\UserRegistrationDTO;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\UserCredential;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthenticationService
{
    /**
     * Password login with identifier (email or mobile).
     */
    public function login(LoginDTO $dto): array
    {
        $user = $this->findUserByIdentifier($dto->identifier);

        if (!$user) {
            throw new HttpException(401, 'اطلاعات ورود نادرست است.');
        }

        // One relation load instead of lazy N+1
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

    public function completeLoginForUser(User $user, ?string $requestedTenantId = null): array
    {
        $memberships = TenantUser::withoutGlobalScopes()
            ->where('user_id', $user->user_id)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->get(['tenant_user_id', 'tenant_id', 'user_id', 'status', 'is_owner']);

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

        $tenantUser = $memberships->firstWhere('tenant_id', $tenantIdToLogin);

        return $this->issueTenantSession($user, $tenantUser);
    }

    public function selectTenant(User $user, string $tenantId): array
    {
        return $this->completeLoginForUser($user, $tenantId);
    }

    /**
     * Issue session with minimal round-trips (3–4 queries total for RBAC).
     */
    private function issueTenantSession(User $user, object $tenantUser): array
    {
        $tenantIdToLogin = $tenantUser->tenant_id;

        if ((int) $tenantUser->status === 2) {
            throw new HttpException(403, 'Your account is suspended in this organization.');
        }

        if ((int) $tenantUser->status !== 1) {
            throw new HttpException(403, 'Your account is not active in this organization.');
        }

        // Roles + codes in one join
        $roleRows = DB::table('tenant_user_roles')
            ->join('tenant_roles', 'tenant_user_roles.tenant_role_id', '=', 'tenant_roles.tenant_role_id')
            ->where('tenant_user_roles.tenant_id', $tenantIdToLogin)
            ->where('tenant_user_roles.user_id', $user->user_id)
            ->whereNull('tenant_user_roles.deleted_at')
            ->whereNull('tenant_roles.deleted_at')
            ->where('tenant_roles.status', 1)
            ->select([
                'tenant_roles.tenant_role_id',
                'tenant_roles.code',
                'tenant_roles.name',
                'tenant_roles.is_system_default',
            ])
            ->get();

        $roles = $roleRows->map(fn ($role) => [
            'role_id'           => $role->tenant_role_id,
            'code'              => $role->code,
            'name'              => $role->name,
            'is_system_default' => (bool) $role->is_system_default,
        ])->values()->toArray();

        $roleIds = $roleRows->pluck('tenant_role_id')->unique()->values()->toArray();

        $permissions = [];
        if ($roleIds !== []) {
            $permissions = DB::table('tenant_role_permissions')
                ->join('tenant_permissions', 'tenant_role_permissions.tenant_permission_id', '=', 'tenant_permissions.tenant_permission_id')
                ->where('tenant_role_permissions.tenant_id', $tenantIdToLogin)
                ->whereIn('tenant_role_permissions.tenant_role_id', $roleIds)
                ->whereNull('tenant_role_permissions.deleted_at')
                ->whereNull('tenant_permissions.deleted_at')
                ->where('tenant_permissions.status', 1)
                ->pluck('tenant_permissions.code')
                ->unique()
                ->values()
                ->toArray();
        }

        $scopes = DB::table('tenant_user_scopes')
            ->join('tenant_scopes', 'tenant_user_scopes.scope_id', '=', 'tenant_scopes.scope_id')
            ->where('tenant_user_scopes.tenant_id', $tenantIdToLogin)
            ->where('tenant_user_scopes.tenant_user_id', $tenantUser->tenant_user_id)
            ->whereNull('tenant_user_scopes.deleted_at')
            ->whereNull('tenant_scopes.deleted_at')
            ->where('tenant_scopes.is_active', true)
            ->select([
                'tenant_scopes.scope_id',
                'tenant_scopes.scope_name',
                'tenant_scopes.scope_type',
                'tenant_scopes.reference_id',
            ])
            ->get()
            ->map(fn ($scope) => [
                'scope_id'     => $scope->scope_id,
                'scope_name'   => $scope->scope_name,
                'scope_type'   => strtoupper((string) $scope->scope_type),
                'reference_id' => $scope->reference_id,
            ])
            ->values()
            ->toArray();

        // Single tenant row
        $tenantRow = Tenant::query()
            ->where('tenant_id', $tenantIdToLogin)
            ->first(['tenant_id', 'tenant_name', 'tenant_code']);

        // Token ops
        $user->tokens()->where('name', 'pre_auth_select_tenant')->delete();

        $tokenName = 'auth_token_tenant_'.$tenantIdToLogin;
        $tokenResult = $user->createToken($tokenName, ['*', 'tenant:'.$tenantIdToLogin]);

        // last_login without full model events if possible
        DB::table('users')
            ->where('user_id', $user->user_id)
            ->update(['last_login_at' => now()]);

        $securityContext = [
            'user_id'        => $user->user_id,
            'tenant_id'      => $tenantIdToLogin,
            'tenant_user_id' => $tenantUser->tenant_user_id,
            'roles'          => $roles,
            'permissions'    => $permissions,
            'scopes'         => $scopes,
            'is_owner'       => (bool) $tenantUser->is_owner,
        ];

        // Warm middleware cache so first dashboard API calls skip RBAC re-load
        Cache::put(
            sprintf('sec_ctx:%s:%s', $tenantIdToLogin, $user->user_id),
            [
                'tenant_user_id' => $tenantUser->tenant_user_id,
                'is_owner'       => (bool) $tenantUser->is_owner,
                'scopes'         => $scopes,
                'roles'          => $roles,
                'permissions'    => $permissions,
            ],
            60
        );

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
                'tenant_name' => $tenantRow?->tenant_name,
                'tenant_code' => $tenantRow?->tenant_code,
            ],
            'security_context' => $securityContext,
        ];
    }

    public function findUserByIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);

        $query = User::query()->whereNull('deleted_at')->with('credential');

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

        LoadUserScopesMiddleware::forget($tenantId, $user->user_id);

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
