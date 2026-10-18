<?php

namespace App\Modules\IdentityCore\Services;

use App\Base\Services\HoldingAccessService;
use App\Modules\IdentityCore\DTOs\CreateTenantUserDTO;
use App\Modules\IdentityCore\DTOs\UpdateTenantUserDTO;
use App\Modules\IdentityCore\DTOs\AssignRoleToUserDTO;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\UserCredential;
use App\Modules\IdentityCore\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;
use Exception;

class UserService
{
    public function __construct(
        private readonly RoleService $roleService,
        private readonly MembershipHistoryService $membershipHistoryService,
        private readonly OrganizationalEmailService $organizationalEmailService,
    ) {}

    public function listTenantUsers(string $membershipFilter = 'active'): Collection
    {
        $tenantId = $this->getTenantId();

        $query = TenantUser::query()
            ->where('tenant_users.tenant_id', $tenantId)
            ->select([
                'tenant_users.tenant_user_id',
                'tenant_users.tenant_id',
                'tenant_users.user_id',
                'tenant_users.is_owner',
                'tenant_users.status',
                'tenant_users.created_at',
                'tenant_users.updated_at',
                'tenant_users.deleted_at',
            ])
            ->with(['user:user_id,first_name,last_name,email,mobile,status,user_kind,created_at']);

        if ($membershipFilter === 'deleted') {
            $query->onlyTrashed()->orderByDesc('deleted_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $users = $query->get();

        $userIds = $users->pluck('user_id')->filter()->unique()->values()->all();
        $rolesByUser = [];
        if ($userIds !== []) {
            $roleRows = DB::table('tenant_user_roles')
                ->join('tenant_roles', 'tenant_user_roles.tenant_role_id', '=', 'tenant_roles.tenant_role_id')
                ->where('tenant_user_roles.tenant_id', $tenantId)
                ->whereIn('tenant_user_roles.user_id', $userIds)
                ->whereNull('tenant_roles.deleted_at')
                ->where('tenant_roles.status', 1)
                ->get([
                    'tenant_user_roles.user_id',
                    'tenant_roles.tenant_role_id',
                    'tenant_roles.name',
                    'tenant_roles.code',
                    'tenant_roles.parent_role_id',
                ]);

            foreach ($roleRows as $row) {
                $uid = (string) $row->user_id;
                $rolesByUser[$uid][] = [
                    'tenant_role_id' => $row->tenant_role_id,
                    'name'           => $row->name,
                    'code'           => $row->code,
                    'parent_role_id' => $row->parent_role_id,
                ];
            }
        }

        foreach ($users as $tu) {
            $uid = (string) $tu->user_id;
            $tu->setAttribute('roles', $rolesByUser[$uid] ?? []);
        }

        $tenantUserIds = $users->pluck('tenant_user_id')->filter()->unique()->values()->all();
        $scopesByTu = [];
        if ($tenantUserIds !== []) {
            $scopeRows = DB::table('tenant_user_scopes')
                ->join('tenant_scopes', 'tenant_user_scopes.scope_id', '=', 'tenant_scopes.scope_id')
                ->where('tenant_user_scopes.tenant_id', $tenantId)
                ->whereIn('tenant_user_scopes.tenant_user_id', $tenantUserIds)
                ->whereNull('tenant_user_scopes.deleted_at')
                ->whereNull('tenant_scopes.deleted_at')
                ->where('tenant_scopes.is_active', true)
                ->get([
                    'tenant_user_scopes.tenant_user_id',
                    'tenant_scopes.scope_id',
                    'tenant_scopes.scope_name',
                    'tenant_scopes.scope_type',
                ]);

            foreach ($scopeRows as $row) {
                $tid = (string) $row->tenant_user_id;
                $scopesByTu[$tid][] = [
                    'scope_id'   => $row->scope_id,
                    'scope_name' => $row->scope_name,
                    'scope_type' => $row->scope_type,
                ];
            }
        }

        foreach ($users as $tu) {
            $tid = (string) $tu->tenant_user_id;
            $tu->setAttribute('scopes', $scopesByTu[$tid] ?? []);
        }

        return $users;
    }

    public function getTenantUser(string $tenantUserId): TenantUser
    {
        $tenantId = $this->getTenantId();

        return TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_user_id', $tenantUserId)
            ->with(['user'])
            ->firstOrFail();
    }

    public function createTenantUser(CreateTenantUserDTO $dto): TenantUser
    {
        $tenantId = $this->getTenantId();
        $mobile = $this->normalizeMobile($dto->mobile);

        if ($mobile === '' || strlen($mobile) < 10) {
            throw new Exception('شماره موبایل معتبر نیست.');
        }

        $this->organizationalEmailService->resolveEmailHost($tenantId);

        return DB::transaction(function () use ($dto, $tenantId, $mobile) {
            $user = User::query()
                ->where('mobile', $mobile)
                ->whereNull('deleted_at')
                ->first();

            if (!$user) {
                if (filled($dto->emailLocalPart)) {
                    $email = $this->organizationalEmailService->buildEmailFromLocalPart(
                        $tenantId,
                        (string) $dto->emailLocalPart
                    );
                } else {
                    $email = $this->organizationalEmailService->generateUniqueEmail(
                        $tenantId,
                        $dto->firstName,
                        $dto->lastName
                    );
                }

                $user = User::create([
                    'first_name' => $dto->firstName,
                    'last_name'  => $dto->lastName,
                    'email'      => $email,
                    'mobile'     => $mobile,
                    'user_kind'  => 1,
                    'status'     => 1,
                ]);

                UserCredential::create([
                    'credential_id'       => (string) Str::uuid(),
                    'user_id'             => $user->user_id,
                    'password_hash'       => null,
                    'must_set_password'   => true,
                    'authentication_type' => 2,
                    'is_verified'         => false,
                    'two_factor_enabled'  => false,
                    'failed_login_count'  => 0,
                ]);
            }

            $existingMembership = TenantUser::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $user->user_id)
                ->first();

            if ($existingMembership && $existingMembership->trashed()) {
                throw new Exception('این کاربر قبلاً از سازمان حذف شده است. از فهرست حذف‌شده‌ها بازگردانی کنید.');
            }

            if ($existingMembership) {
                throw new Exception('این کاربر هم‌اکنون عضو این سازمان است.');
            }

            $isOwner = false;
            if ($dto->isOwner) {
                $this->assertActorIsTenantOwner($tenantId);
                $isOwner = true;
            }

            $homeCompanyId = $this->resolveHomeCompanyIdForCreate($tenantId);

            $tenantUser = TenantUser::create([
                'tenant_id'       => $tenantId,
                'user_id'         => $user->user_id,
                'is_owner'        => $isOwner,
                'home_company_id' => $homeCompanyId,
                'status'          => 1,
            ]);

            $this->membershipHistoryService->recordChange(
                $tenantUser->tenant_user_id,
                null,
                1,
                'JOIN',
                'عضویت در سازمان',
                $this->currentActorUserId()
            );

            $this->logEventOutbox(
                $tenantId,
                'tenant_users',
                $tenantUser->tenant_user_id,
                'identity.tenant_user.created.v1',
                [
                    'tenant_user_id' => $tenantUser->tenant_user_id,
                    'user_id'        => $user->user_id,
                    'email'          => $user->email,
                    'mobile'         => $user->mobile,
                ]
            );

            if (!empty($dto->roleIds)) {
                foreach ($dto->roleIds as $roleId) {
                    $assignDto = AssignRoleToUserDTO::fromRequest([
                        'user_id'  => $user->user_id,
                        'role_ids' => [$roleId],
                    ]);
                    $this->roleService->assignRoleToUser($assignDto);
                }
            }

            return $tenantUser->load('user');
        });
    }

    public function updateTenantUser(UpdateTenantUserDTO $dto): TenantUser
    {
        $tenantId = $this->getTenantId();

        return DB::transaction(function () use ($dto, $tenantId) {
            $tenantUser = TenantUser::query()
                ->where('tenant_id', $tenantId)
                ->where('tenant_user_id', $dto->tenantUserId)
                ->with(['user'])
                ->firstOrFail();

            $this->assertCanManageTenantUserId((string) $tenantUser->tenant_user_id);

            $previousStatus = $tenantUser->status;
            $actorUserId = $this->currentActorUserId();

            $membershipChanges = array_filter([
                'is_owner' => $dto->isOwner,
                'status'   => $dto->status,
            ], fn ($value) => !is_null($value));

            if (
                array_key_exists('is_owner', $membershipChanges)
                && (bool) $membershipChanges['is_owner'] !== (bool) $tenantUser->is_owner
            ) {
                $this->assertActorIsTenantOwner($tenantId);
            }

            if (
                $actorUserId
                && $actorUserId === (string) $tenantUser->user_id
                && array_key_exists('status', $membershipChanges)
                && (int) $membershipChanges['status'] === 0
            ) {
                throw new Exception('نمی‌توانید عضویت خودتان را غیرفعال کنید.');
            }

            if (
                array_key_exists('status', $membershipChanges)
                && (int) $membershipChanges['status'] === 0
                && (bool) $tenantUser->is_owner
            ) {
                $this->assertNotLastActiveOwner($tenantId, $tenantUser->tenant_user_id);
            }

            if (
                array_key_exists('is_owner', $membershipChanges)
                && $membershipChanges['is_owner'] === false
                && (bool) $tenantUser->is_owner
            ) {
                $this->assertNotLastActiveOwner($tenantId, $tenantUser->tenant_user_id);
            }

            if (!empty($membershipChanges)) {
                if (property_exists($tenantUser, 'row_version') || isset($tenantUser->row_version)) {
                    $membershipChanges['row_version'] = ((int) ($tenantUser->row_version ?? 1)) + 1;
                }
                $tenantUser->update($membershipChanges);
            }

            if (array_key_exists('status', $membershipChanges)
                && (int) $membershipChanges['status'] !== (int) $previousStatus
            ) {
                $this->membershipHistoryService->recordChange(
                    $tenantUser->tenant_user_id,
                    (int) $previousStatus,
                    (int) $membershipChanges['status'],
                    'STATUS_CHANGE',
                    'تغییر وضعیت عضویت',
                    $actorUserId
                );
            }

            $userChanges = array_filter([
                'first_name' => $dto->firstName,
                'last_name'  => $dto->lastName,
                'mobile'     => $dto->mobile !== null ? $this->normalizeMobile($dto->mobile) : null,
            ], fn ($value) => !is_null($value));

            if ($dto->emailLocalPart !== null && $tenantUser->user) {
                $local = $this->organizationalEmailService->sanitizeLocalPart($dto->emailLocalPart);
                if ($local === '') {
                    throw new Exception('بخش ابتدایی ایمیل معتبر نیست.');
                }
                $host = $this->organizationalEmailService->resolveEmailHost($tenantId);
                $newEmail = $local.'@'.$host;
                $currentEmail = (string) ($tenantUser->user->email ?? '');
                if (strcasecmp($newEmail, $currentEmail) !== 0) {
                    $taken = User::query()
                        ->where('email', $newEmail)
                        ->where('user_id', '!=', $tenantUser->user_id)
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($taken) {
                        throw new Exception('این آدرس ایمیل قبلاً ثبت شده است. بخش ابتدایی را تغییر دهید.');
                    }
                    $userChanges['email'] = $newEmail;
                }
            }

            if (!empty($userChanges) && $tenantUser->user) {
                $tenantUser->user->update($userChanges);

                $parts = [];
                if (array_key_exists('first_name', $userChanges) || array_key_exists('last_name', $userChanges)) {
                    $parts[] = 'نام';
                }
                if (array_key_exists('mobile', $userChanges)) {
                    $parts[] = 'موبایل';
                }
                if (array_key_exists('email', $userChanges)) {
                    $parts[] = 'ایمیل';
                }
                $desc = $parts === []
                    ? 'به‌روزرسانی اطلاعات هویتی'
                    : 'ویرایش ' . implode('، ', $parts);

                $this->membershipHistoryService->recordChange(
                    $tenantUser->tenant_user_id,
                    (int) $tenantUser->status,
                    (int) $tenantUser->status,
                    'IDENTITY_UPDATE',
                    $desc,
                    $actorUserId
                );
            }

            $this->logEventOutbox(
                $tenantId,
                'tenant_users',
                $tenantUser->tenant_user_id,
                'identity.tenant_user.updated.v1',
                [
                    'tenant_user_id'     => $tenantUser->tenant_user_id,
                    'membership_changes' => $membershipChanges,
                    'user_changes'       => $userChanges,
                ]
            );

            return $tenantUser->fresh(['user']);
        });
    }

    public function softDeleteTenantUser(string $tenantUserId): void
    {
        $tenantId = $this->getTenantId();

        DB::transaction(function () use ($tenantUserId, $tenantId) {
            $tenantUser = TenantUser::query()
                ->where('tenant_id', $tenantId)
                ->where('tenant_user_id', $tenantUserId)
                ->firstOrFail();

            $this->assertCanManageTenantUserId((string) $tenantUser->tenant_user_id);

            $actorUserId = $this->currentActorUserId();

            if ($actorUserId && $actorUserId === (string) $tenantUser->user_id) {
                throw new Exception('نمی‌توانید عضویت خودتان را حذف کنید.');
            }

            if ((bool) $tenantUser->is_owner) {
                $this->assertNotLastActiveOwner($tenantId, $tenantUser->tenant_user_id);
            }

            $previousStatus = $tenantUser->status;
            $userId = (string) $tenantUser->user_id;

            $tenantUser->forceFill([
                'status'   => 0,
                'is_owner' => false,
            ])->save();

            $this->membershipHistoryService->recordChange(
                $tenantUserId,
                (int) $previousStatus,
                0,
                'SOFT_DELETE',
                'حذف از سازمان',
                $actorUserId
            );

            $tenantUser->delete();

            $this->revokeUserAccessTokens($userId);

            $this->logEventOutbox(
                $tenantId,
                'tenant_users',
                $tenantUserId,
                'identity.tenant_user.deleted.v1',
                ['tenant_user_id' => $tenantUserId, 'user_id' => $userId]
            );
        });
    }

    public function restoreTenantUser(string $tenantUserId): TenantUser
    {
        $tenantId = $this->getTenantId();

        return DB::transaction(function () use ($tenantUserId, $tenantId) {
            $tenantUser = TenantUser::onlyTrashed()
                ->where('tenant_id', $tenantId)
                ->where('tenant_user_id', $tenantUserId)
                ->firstOrFail();

            $this->assertCanManageTenantUserId((string) $tenantUser->tenant_user_id);

            $tenantUser->restore();

            $tenantUser->update([
                'status'      => 0,
                'is_owner'    => false,
                'row_version' => ((int) ($tenantUser->row_version ?? 1)) + 1,
            ]);

            $this->membershipHistoryService->recordChange(
                $tenantUserId,
                0,
                0,
                'RESTORE',
                'بازگردانی به فهرست سازمان',
                $this->currentActorUserId()
            );

            $this->logEventOutbox(
                $tenantId,
                'tenant_users',
                $tenantUserId,
                'identity.tenant_user.restored.v1',
                ['tenant_user_id' => $tenantUserId]
            );

            return $tenantUser->fresh(['user']);
        });
    }

    private function normalizeMobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';
        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    /**
     * IDORG-T10 / G06 — company operators may mutate only members inside their COMPANY scopes.
     * ADR-ID-ORG-005 L01/L04 — also enforce home_company ceiling when set.
     */
    private function assertCanManageTenantUserId(string $tenantUserId): void
    {
        // includes ADR-ID-ORG-005 home ceiling check inside HoldingAccessService
        app(HoldingAccessService::class)->assertCanManageTenantUser($tenantUserId);
    }

    /**
     * ADR-ID-ORG-005 L01 — anchor new membership under actor company ceiling root.
     */
    private function resolveHomeCompanyIdForCreate(string $tenantId): ?string
    {
        try {
            $holding = app(HoldingAccessService::class);
            if ($holding->isIdentityMutateGroupWide()) {
                $primary = DB::table('erp_companies')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->orderByRaw('CASE WHEN is_primary IS TRUE THEN 0 ELSE 1 END')
                    ->orderBy('created_at')
                    ->value('company_id');

                return $primary ? (string) $primary : null;
            }
            $ids = $holding->actorCompanyIds();
            if ($ids !== []) {
                return (string) $ids[0];
            }
            $ceiling = $holding->actorCeilingCompanyIds();

            return $ceiling[0] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function getTenantId(): string
    {
        if (app()->bound('current_tenant_id') && app('current_tenant_id')) {
            return (string) app('current_tenant_id');
        }

        return (string) (\App\Base\Context\TenantContext::getInstance()->getTenantId() ?? '');
    }

    private function currentActorUserId(): ?string
    {
        $ctx = \Illuminate\Support\Facades\Context::get('security_context');
        if (is_array($ctx) && !empty($ctx['user_id'])) {
            return (string) $ctx['user_id'];
        }

        return null;
    }

    private function assertActorIsTenantOwner(string $tenantId): void
    {
        $ctx = \Illuminate\Support\Facades\Context::get('security_context');
        if (is_array($ctx) && !empty($ctx['is_owner'])) {
            return;
        }

        throw new Exception('فقط مالک فنی سازمان می‌تواند مالک جدید تعیین کند.');
    }

    private function assertNotLastActiveOwner(string $tenantId, string $exceptTenantUserId): void
    {
        $otherOwners = TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->where('is_owner', true)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->where('tenant_user_id', '!=', $exceptTenantUserId)
            ->count();

        if ($otherOwners < 1) {
            throw new Exception('نمی‌توان آخرین مالک فعال سازمان را غیرفعال یا حذف کرد.');
        }
    }

    private function revokeUserAccessTokens(string $userId): void
    {
        try {
            DB::table('oauth_access_tokens')->where('user_id', $userId)->delete();
        } catch (\Throwable) {
        }
    }

    private function logEventOutbox(
        string $tenantId,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload
    ): void {
        try {
            if (class_exists(\App\Base\Services\EventOutboxService::class)) {
                app(\App\Base\Services\EventOutboxService::class)->append(
                    $tenantId,
                    $aggregateType,
                    $aggregateId,
                    $eventType,
                    $payload
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('event_outbox_append_failed', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
