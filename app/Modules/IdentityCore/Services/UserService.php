<?php

namespace App\Modules\IdentityCore\Services;

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
            ->with(['user:user_id,first_name,last_name,email,mobile,status,user_kind,created_at']);

        if ($membershipFilter === 'deleted') {
            $query->onlyTrashed()->orderByDesc('deleted_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $users = $query->get();

        // Attach assigned roles (for members list primary-role column)
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

        // Attach assigned scopes (for members list column)
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

            $tenantUser = TenantUser::create([
                'tenant_id'  => $tenantId,
                'user_id'    => $user->user_id,
                'is_owner'   => $isOwner,
                'status'     => 1,
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

    private function assertActorIsTenantOwner(string $tenantId): void
    {
        $actorUserId = $this->currentActorUserId();
        if (!$actorUserId) {
            throw new Exception('احراز هویت برای تغییر مالک سازمان الزامی است.');
        }

        $isOwner = TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $actorUserId)
            ->where('is_owner', true)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->exists();

        if (!$isOwner) {
            throw new Exception(
                'فقط مالک فعلی سازمان می‌تواند مدیر اصلی (مالک) را تعیین یا لغو کند.'
            );
        }
    }

    private function assertNotLastActiveOwner(string $tenantId, string $excludeTenantUserId): void
    {
        $otherActiveOwners = TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->where('is_owner', true)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->where('tenant_user_id', '!=', $excludeTenantUserId)
            ->count();

        if ($otherActiveOwners < 1) {
            throw new Exception(
                'آخرین مدیر اصلی فعال سازمان را نمی‌توان غیرفعال یا حذف کرد. ابتدا مدیر اصلی دیگری تعیین کنید.'
            );
        }
    }

    private function currentActorUserId(): ?string
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return (string) ($user->user_id ?? $user->getAuthIdentifier());
    }

    private function revokeUserAccessTokens(string $userId): void
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('personal_access_tokens')) {
                return;
            }
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $userId)
                ->delete();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('token revoke failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getTenantId(): string
    {
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        if (!$tenantId) {
            throw new Exception('Tenant Context is missing. Architecture Violation.');
        }

        return $tenantId;
    }

    private function logEventOutbox(
        string $tenantId,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload
    ): void {
        try {
            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => $aggregateType,
                'aggregate_id'   => $aggregateId,
                'event_type'     => $eventType,
                'payload'        => json_encode($payload),
                'status'         => 1,
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('event_outbox write failed', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
