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
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthenticationService
{
    /**
     * Login and return full security context according to Architecture Law 4.4
     */
    public function login(LoginDTO $dto): array
    {
        $user = User::where('email', $dto->email)
            ->whereNull('deleted_at')
            ->first();

        if (!$user) {
            throw new HttpException(401, 'ایمیل یا رمز عبور اشتباه است.');
        }

        $credential = $user->credential;

        // Lockout check (P4-S1)
        if ($credential && $credential->locked_until && $credential->locked_until->isFuture()) {
            throw new HttpException(403, 'حساب کاربری موقتاً قفل شده است. لطفاً بعداً تلاش کنید.');
        }

        if (!$credential || !Hash::check($dto->password, $credential->password_hash)) {
            if ($credential) {
                $this->registerFailedLoginAttempt($credential);
            }
            throw new HttpException(401, 'ایمیل یا رمز عبور اشتباه است.');
        }

        if ((int) $user->status !== 1) {
            throw new HttpException(403, 'حساب کاربری شما غیرفعال یا مسدود شده است.');
        }

        $this->clearFailedLoginAttempts($credential);

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

            if ((int) $tenantUser->status === 2) {
                throw new HttpException(403, 'Your account is suspended in this organization.');
            }

            if ((int) $tenantUser->status !== 1) {
                throw new HttpException(403, 'Your account is not active in this organization.');
            }

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

            $scopeModels = TenantUserScope::withoutGlobalScopes()
                ->where('tenant_id', $tenantIdToLogin)
                ->where('tenant_user_id', $tenantUser->tenant_user_id)
                ->whereNull('deleted_at')
                ->get();

            if ($scopeModels->isNotEmpty()) {
                $scopeIds = $scopeModels->pluck('tenant_scope_id')->unique()->values()->toArray();
                $scopesData = TenantScope::withoutGlobalScopes()
                    ->where('tenant_id', $tenantIdToLogin)
                    ->whereIn('tenant_scope_id', $scopeIds)
                    ->whereNull('deleted_at')
                    ->get();

                $scopes = $scopesData->map(function ($scope) {
                    return [
                        'scope_id'     => $scope->tenant_scope_id,
                        'scope_name'   => $scope->scope_name ?? $scope->name ?? null,
                        'scope_type'   => $scope->scope_type ?? null,
                        'reference_id' => $scope->reference_id ?? null,
                    ];
                })->values()->toArray();
            }
        }

        $tokenAbilities = ['*'];
        $tokenName = 'auth_token';

        if ($tenantIdToLogin) {
            $tokenName .= '_tenant_' . $tenantIdToLogin;
            $tokenAbilities[] = 'tenant:' . $tenantIdToLogin;
        }

        $tokenResult = $user->createToken($tokenName, $tokenAbilities);

        $user->last_login_at = now();
        $user->save();

        $securityContext = [
            'user_id'        => $user->user_id,
            'tenant_id'      => $tenantIdToLogin,
            'tenant_user_id' => $tenantUser?->tenant_user_id,
            'roles'          => $roles,
            'permissions'    => $permissions,
            'scopes'         => $scopes,
            'is_owner'       => $tenantUser ? (bool) $tenantUser->is_owner : false,
        ];

        if ($tenantIdToLogin) {
            $this->writeOutboxEvent(
                tenantId: $tenantIdToLogin,
                aggregateId: $user->user_id,
                eventType: 'identity.user.logged_in.v1',
                payload: [
                    'user_id'        => $user->user_id,
                    'tenant_id'      => $tenantIdToLogin,
                    'tenant_user_id' => $tenantUser?->tenant_user_id,
                    'token_name'     => $tokenName,
                    'occurred_at'    => now()->toIso8601String(),
                ]
            );
        }

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
            'security_context' => $securityContext,
        ];
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

    /**
     * P4-S1: after 5 failed attempts lock account for 15 minutes.
     */
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
