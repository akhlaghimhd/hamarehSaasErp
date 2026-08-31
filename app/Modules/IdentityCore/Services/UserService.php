<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\DTOs\CreateTenantUserDTO;
use App\Modules\IdentityCore\DTOs\UpdateTenantUserDTO;
use App\Modules\IdentityCore\DTOs\AssignRoleToUserDTO;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\UserCredential;
use App\Modules\IdentityCore\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;
use Exception;

class UserService
{
    public function __construct(
        private readonly RoleService $roleService,
        private readonly MembershipHistoryService $membershipHistoryService
    ) {}

    /**
     * List tenant memberships for the current tenant.
     */
    public function listTenantUsers(): Collection
    {
        $tenantId = $this->getTenantId();

        return TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Show one tenant membership in the current tenant.
     */
    public function getTenantUser(string $tenantUserId): TenantUser
    {
        $tenantId = $this->getTenantId();

        return TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_user_id', $tenantUserId)
            ->with(['user'])
            ->firstOrFail();
    }

    /**
     * Create user (if needed) + tenant membership (+ optional roles).
     */
    public function createTenantUser(CreateTenantUserDTO $dto): TenantUser
    {
        $tenantId = $this->getTenantId();

        return DB::transaction(function () use ($dto, $tenantId) {
            $user = User::where('email', $dto->email)->first();

            if (!$user) {
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
                    'authentication_type' => 1,
                    'is_verified'         => false,
                    'two_factor_enabled'  => false,
                    'failed_login_count'  => 0,
                ]);
            }

            $existingMembership = TenantUser::where('tenant_id', $tenantId)
                ->where('user_id', $user->user_id)
                ->first();

            if ($existingMembership) {
                throw new Exception('User is already a member of this tenant.');
            }

            $tenantUser = TenantUser::create([
                'tenant_id'  => $tenantId,
                'user_id'    => $user->user_id,
                'is_owner'   => $dto->isOwner,
                'status'     => 1,
            ]);

            // Initial membership history (join)
            $this->membershipHistoryService->recordChange(
                $tenantUser->tenant_user_id,
                null,
                1,
                'JOIN',
                'Tenant membership created'
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

    /**
     * Update tenant membership fields and related user profile fields.
     * Soft-delete is handled by softDeleteTenantUser().
     */
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

            $membershipChanges = array_filter([
                'is_owner' => $dto->isOwner,
                'status'   => $dto->status,
            ], fn ($value) => !is_null($value));

            if (!empty($membershipChanges)) {
                if (property_exists($tenantUser, 'row_version') || isset($tenantUser->row_version)) {
                    $membershipChanges['row_version'] = ((int) ($tenantUser->row_version ?? 1)) + 1;
                }
                $tenantUser->update($membershipChanges);
            }

            // Record status transition when status actually changes
            if (array_key_exists('status', $membershipChanges)
                && (int) $membershipChanges['status'] !== (int) $previousStatus
            ) {
                $this->membershipHistoryService->recordChange(
                    $tenantUser->tenant_user_id,
                    (int) $previousStatus,
                    (int) $membershipChanges['status'],
                    'STATUS_CHANGE',
                    'Membership status updated via API'
                );
            }

            $userChanges = array_filter([
                'first_name' => $dto->firstName,
                'last_name'  => $dto->lastName,
                'mobile'     => $dto->mobile,
            ], fn ($value) => !is_null($value));

            if (!empty($userChanges) && $tenantUser->user) {
                $tenantUser->user->update($userChanges);
            }

            $this->logEventOutbox(
                $tenantId,
                'tenant_users',
                $tenantUser->tenant_user_id,
                'identity.tenant_user.updated.v1',
                [
                    'tenant_user_id'      => $tenantUser->tenant_user_id,
                    'membership_changes'  => $membershipChanges,
                    'user_changes'        => $userChanges,
                ]
            );

            return $tenantUser->fresh(['user']);
        });
    }

    /**
     * Soft-delete a tenant membership (Law 1.4 — no physical delete).
     * History is recorded BEFORE soft-delete so TenantUser is still visible.
     */
    public function softDeleteTenantUser(string $tenantUserId): void
    {
        $tenantId = $this->getTenantId();

        DB::transaction(function () use ($tenantUserId, $tenantId) {
            $tenantUser = TenantUser::query()
                ->where('tenant_id', $tenantId)
                ->where('tenant_user_id', $tenantUserId)
                ->firstOrFail();

            $previousStatus = $tenantUser->status;

            // Record audit row while membership is still active
            $this->membershipHistoryService->recordChange(
                $tenantUserId,
                (int) $previousStatus,
                0,
                'SOFT_DELETE',
                'Tenant membership soft-deleted'
            );

            $tenantUser->delete();

            $this->logEventOutbox(
                $tenantId,
                'tenant_users',
                $tenantUserId,
                'identity.tenant_user.deleted.v1',
                ['tenant_user_id' => $tenantUserId]
            );
        });
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
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $tenantId,
            'aggregate_type' => $aggregateType,
            'aggregate_id'   => $aggregateId,
            'event_type'     => $eventType,
            'payload'        => json_encode($payload),
            'status'         => 1,
            'created_at'     => now(),
        ]);
    }
}
