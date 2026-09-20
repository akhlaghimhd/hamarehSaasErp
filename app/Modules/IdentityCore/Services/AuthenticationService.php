<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\DTOs\LoginDTO;
use App\Modules\IdentityCore\DTOs\UserRegistrationDTO;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\UserCredential;
use App\Modules\IdentityCore\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthenticationService
{
    public function __construct(
        private readonly PasswordPolicyService $passwordPolicy,
    ) {
    }

    public function login(LoginDTO $dto): array
    {
        $user = $this->findUserByIdentifier($dto->identifier);

        if (!$user) {
            throw new HttpException(401, 'اطلاعات ورود نادرست است.');
        }

        $credential = $user->credential;

        if ($credential && $credential->locked_until && $credential->locked_until->isFuture()) {
            throw new HttpException(423, 'حساب موقتاً قفل شده است. کمی بعد تلاش کنید.');
        }

        // New users (must_set_password / no hash) cannot use password login — OTP only
        if (!$credential || $credential->password_hash === null || $credential->must_set_password) {
            throw new HttpException(
                401,
                'برای اولین ورود از کد یکبارمصرف (OTP) استفاده کنید و سپس رمز عبور را تعیین نمایید.'
            );
        }

        if (!Hash::check($dto->password, $credential->password_hash)) {
            $this->registerFailedLoginAttempt($credential);
            throw new HttpException(401, 'اطلاعات ورود نادرست است.');
        }

        if ((int) $user->status !== 1) {
            throw new HttpException(403, 'حساب کاربری غیرفعال است.');
        }

        $this->clearFailedLoginAttempts($credential);

        return $this->completeLoginForUser($user, $dto->tenantId);
    }

    public function completeLoginForUser(User $user, ?string $requestedTenantId = null): array
    {
        // Force set-password path for first login / must_set_password
        $credential = $user->credential;
        if ($credential && (bool) $credential->must_set_password) {
            return $this->issueSetPasswordSession($user);
        }

        $memberships = TenantUser::withoutGlobalScopes()
            ->where('user_id', $user->user_id)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->orderByDesc('is_owner')
            ->orderByDesc('updated_at')
            ->get(['tenant_user_id', 'tenant_id', 'user_id', 'status', 'is_owner']);

        $memberships = $memberships->unique('tenant_id')->values();

        if ($memberships->isEmpty()) {
            throw new HttpException(403, 'عضویت فعالی در هیچ سازمانی برای این کاربر یافت نشد.');
        }

        $tenantIdToLogin = $requestedTenantId;

        if ($tenantIdToLogin) {
            $allowed = $memberships->firstWhere('tenant_id', $tenantIdToLogin);
            if (!$allowed) {
                $any = TenantUser::withoutGlobalScopes()
                    ->where('user_id', $user->user_id)
                    ->where('tenant_id', $tenantIdToLogin)
                    ->orderByDesc('updated_at')
                    ->first(['status', 'deleted_at']);
                if ($any && $any->deleted_at) {
                    throw new HttpException(403, 'عضویت شما در این سازمان حذف شده است.');
                }
                if ($any && (int) $any->status !== 1) {
                    throw new HttpException(403, 'عضویت شما در این سازمان غیرفعال است.');
                }
                throw new HttpException(403, 'شما عضو این سازمان نیستید.');
            }
        } elseif ($memberships->count() === 1) {
            $tenantIdToLogin = $memberships->first()->tenant_id;
        } else {
            $tenantIds = $memberships->pluck('tenant_id')->unique()->values()->all();
            $tenants = DB::table('tenants')
                ->whereIn('tenant_id', $tenantIds)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->get(['tenant_id', 'tenant_code', 'tenant_name', 'slug']);

            if ($tenants->isEmpty()) {
                throw new HttpException(
                    403,
                    'سازمان فعالی برای عضویت‌های شما یافت نشد. با پشتیبانی تماس بگیرید.'
                );
            }

            if ($tenants->count() === 1) {
                $tenantIdToLogin = $tenants->first()->tenant_id;
                $tenantUser = $memberships->firstWhere('tenant_id', $tenantIdToLogin);

                return $this->issueTenantSession($user, $tenantUser);
            }

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
     * Limited session used only to set password (first login / after OTP when must_set_password).
     */
    private function issueSetPasswordSession(User $user): array
    {
        // Revoke any previous set-password tokens for cleanliness
        try {
            $user->tokens()->where('name', 'set_password')->delete();
        } catch (\Throwable) {
            // ignore
        }

        $tokenResult = $user->createToken('set_password', ['set-password']);

        return [
            'must_set_password' => true,
            'access_token'      => $tokenResult->plainTextToken,
            'token'             => $tokenResult->plainTextToken,
            'token_type'        => 'Bearer',
            'requires_tenant_selection' => false,
            'user' => [
                'user_id'    => $user->user_id,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => $user->email,
                'mobile'     => $user->mobile,
            ],
            'message' => 'لطفاً رمز عبور خود را تعیین کنید.',
        ];
    }

    private function issueTenantSession(User $user, object $tenantUser): array
    {
        $tenantIdToLogin = $tenantUser->tenant_id;

        $fresh = TenantUser::withoutGlobalScopes()
            ->where('tenant_user_id', $tenantUser->tenant_user_id)
            ->where('tenant_id', $tenantIdToLogin)
            ->where('user_id', $user->user_id)
            ->whereNull('deleted_at')
            ->first(['tenant_user_id', 'tenant_id', 'user_id', 'status', 'is_owner']);

        if (!$fresh) {
            throw new HttpException(403, 'عضویت شما در این سازمان حذف شده یا معتبر نیست.');
        }
        $tenantUser = $fresh;

        if ((int) $tenantUser->status === 2) {
            throw new HttpException(403, 'حساب شما در این سازمان معلق است.');
        }

        if ((int) $tenantUser->status !== 1) {
            throw new HttpException(403, 'حساب شما در این سازمان فعال نیست.');
        }

        $roleRows = DB::table('tenant_user_roles')
            ->join('tenant_roles', 'tenant_user_roles.tenant_role_id', '=', 'tenant_roles.tenant_role_id')
            ->where('tenant_user_roles.tenant_id', $tenantIdToLogin)
            ->where('tenant_user_roles.user_id', $user->user_id)
            ->whereNull('tenant_roles.deleted_at')
            ->where('tenant_roles.status', 1)
            ->get([
                'tenant_roles.tenant_role_id',
                'tenant_roles.code',
                'tenant_roles.name',
                'tenant_roles.is_system_default',
            ]);

        $roles = $roleRows->map(fn ($role) => [
            'role_id'           => $role->tenant_role_id,
            'code'              => $role->code,
            'name'              => $role->name,
            'is_system_default' => (bool) ($role->is_system_default ?? false),
        ])->values()->all();

        $roleIds = $roleRows->pluck('tenant_role_id')->unique()->values()->toArray();

        $permissions = [];
        if ($roleIds !== []) {
            $permissions = DB::table('tenant_role_permissions')
                ->join('tenant_permissions', 'tenant_role_permissions.tenant_permission_id', '=', 'tenant_permissions.tenant_permission_id')
                ->where('tenant_role_permissions.tenant_id', $tenantIdToLogin)
                ->whereIn('tenant_role_permissions.tenant_role_id', $roleIds)
                ->whereNull('tenant_permissions.deleted_at')
                ->where('tenant_permissions.status', 1)
                ->pluck('tenant_permissions.code')
                ->unique()
                ->values()
                ->all();
        }

        if ((bool) $tenantUser->is_owner) {
            $ownerPerms = DB::table('tenant_permissions')
                ->where('tenant_id', $tenantIdToLogin)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->pluck('code')
                ->all();
            $permissions = array_values(array_unique(array_merge($permissions, $ownerPerms)));
        }

        // Column on tenant_scopes is reference_id (not resource_id)
        $scopes = DB::table('tenant_user_scopes')
            ->join('tenant_scopes', 'tenant_user_scopes.scope_id', '=', 'tenant_scopes.scope_id')
            ->where('tenant_user_scopes.tenant_id', $tenantIdToLogin)
            ->where('tenant_user_scopes.tenant_user_id', $tenantUser->tenant_user_id)
            ->whereNull('tenant_scopes.deleted_at')
            ->get([
                'tenant_scopes.scope_id',
                'tenant_scopes.scope_type',
                'tenant_scopes.reference_id',
            ])
            ->map(fn ($s) => [
                'scope_id'     => $s->scope_id,
                'scope_type'   => $s->scope_type,
                'reference_id' => $s->reference_id,
            ])
            ->values()
            ->all();

        $tokenResult = $user->createToken('tenant_session', ['*', 'tenant:'.$tenantIdToLogin]);
        $token = $tokenResult->plainTextToken;

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

        Cache::put(
            sprintf('sec_ctx:%s:%s', $tenantIdToLogin, $user->user_id),
            [
                'tenant_user_id' => $tenantUser->tenant_user_id,
                'is_owner'       => (bool) $tenantUser->is_owner,
                'roles'          => $roles,
                'permissions'    => $permissions,
                'scopes'         => $scopes,
            ],
            60
        );

        $org = DB::table('tenants')
            ->where('tenant_id', $tenantIdToLogin)
            ->first(['tenant_id', 'tenant_code', 'tenant_name']);

        return [
            'access_token'              => $token,
            'token'                     => $token,
            'token_type'                => 'Bearer',
            'expires_in'                => null,
            'requires_tenant_selection' => false,
            'must_set_password'         => false,
            'user' => [
                'user_id'        => $user->user_id,
                'first_name'     => $user->first_name,
                'last_name'      => $user->last_name,
                'email'          => $user->email,
                'mobile'         => $user->mobile,
                'tenant_user_id' => $tenantUser->tenant_user_id,
            ],
            'active_tenant_id' => $tenantIdToLogin,
            'tenant_id'        => $tenantIdToLogin,
            'organization' => [
                'tenant_id'   => $tenantIdToLogin,
                'tenant_name' => $org->tenant_name ?? null,
                'tenant_code' => $org->tenant_code ?? null,
            ],
            'security_context' => $securityContext,
        ];
    }

    /**
     * First-login / limited-token set password. Clears must_set_password and revokes the token.
     * Caller must ensure the request is authenticated with set-password ability (or equivalent).
     */
    public function setPassword(User $user, string $newPassword): void
    {
        $credential = $user->credential;
        if (!$credential) {
            throw new HttpException(400, 'اطلاعات امنیتی کاربر یافت نشد.');
        }

        $this->passwordPolicy->assertValid($newPassword, $user);

        $credential->password_hash = Hash::make($newPassword);
        $credential->must_set_password = false;
        $credential->authentication_type = 1;
        $credential->last_password_change_at = now();
        $credential->failed_login_count = 0;
        $credential->locked_until = null;
        $credential->save();

        // Force re-login: delete current + any set_password tokens
        try {
            $user->tokens()->delete();
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * Authenticated change-password (profile). Requires current password.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        $credential = $user->credential;
        if (!$credential || $credential->password_hash === null) {
            throw new HttpException(400, 'هنوز رمزی برای این حساب تعیین نشده است.');
        }

        if (!Hash::check($currentPassword, $credential->password_hash)) {
            throw new HttpException(401, 'رمز عبور فعلی نادرست است.');
        }

        $this->passwordPolicy->assertValid($newPassword, $user);

        if (Hash::check($newPassword, $credential->password_hash)) {
            throw new HttpException(422, 'رمز عبور جدید نباید با رمز فعلی یکسان باشد.');
        }

        $credential->password_hash = Hash::make($newPassword);
        $credential->must_set_password = false;
        $credential->last_password_change_at = now();
        $credential->save();
    }

    /**
     * Forgot-password: set new password after OTP was verified by OtpLoginService (or dedicated confirm).
     * Does not require prior session; used by forgot-password confirm endpoint.
     */
    public function resetPasswordByUser(User $user, string $newPassword): void
    {
        $credential = $user->credential;
        if (!$credential) {
            // Create credential if missing (should be rare)
            $credential = UserCredential::create([
                'credential_id'       => (string) Str::uuid(),
                'user_id'             => $user->user_id,
                'password_hash'       => null,
                'must_set_password'   => true,
                'authentication_type' => 1,
                'is_verified'         => false,
                'two_factor_enabled'  => false,
                'failed_login_count'  => 0,
            ]);
        }

        $this->passwordPolicy->assertValid($newPassword, $user);

        $credential->password_hash = Hash::make($newPassword);
        $credential->must_set_password = false;
        $credential->authentication_type = 1;
        $credential->last_password_change_at = now();
        $credential->failed_login_count = 0;
        $credential->locked_until = null;
        $credential->save();

        try {
            $user->tokens()->delete();
        } catch (\Throwable) {
            // ignore
        }
    }

    public function findUserByIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);
        $query = User::query()->whereNull('deleted_at')->with('credential');

        if (str_contains($identifier, '@')) {
            return $query->where('email', $identifier)->first();
        }

        $mobile = $this->normalizeIranMobile($identifier);
        if ($mobile === null) {
            return null;
        }

        return $query->where('mobile', $mobile)->first();
    }

    private function normalizeIranMobile(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '0'.$digits;
        }
        if (strlen($digits) !== 11 || !str_starts_with($digits, '09')) {
            return null;
        }

        return $digits;
    }

    public function logout(User $user, string $tenantId, ?string $tenantUserId = null): void
    {
        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        Cache::forget(sprintf('sec_ctx:%s:%s', $tenantId, $user->user_id));
    }

    public function register(UserRegistrationDTO $dto): User
    {
        return DB::transaction(function () use ($dto) {
            $user = User::create([
                'first_name' => $dto->firstName,
                'last_name'  => $dto->lastName,
                'email'      => $dto->email,
                'mobile'     => $dto->mobile,
                'user_kind'  => 1,
                'status'     => 1,
            ]);

            UserCredential::create([
                'credential_id'       => (string) Str::uuid(),
                'user_id'             => $user->user_id,
                'password_hash'       => Hash::make($dto->password),
                'must_set_password'   => false,
                'authentication_type' => 1,
                'is_verified'         => false,
                'two_factor_enabled'  => false,
                'failed_login_count'  => 0,
            ]);

            return $user;
        });
    }

    private function registerFailedLoginAttempt(UserCredential $credential): void
    {
        $count = (int) $credential->failed_login_count + 1;
        $credential->failed_login_count = $count;
        if ($count >= 5) {
            $credential->locked_until = now()->addMinutes(15);
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
}
