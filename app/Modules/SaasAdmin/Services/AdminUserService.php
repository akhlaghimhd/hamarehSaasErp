<?php

namespace App\Modules\SaasAdmin\Services;

use App\Modules\SaasAdmin\Models\AdminUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class AdminUserService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function list(): Collection
    {
        return AdminUser::query()
            ->whereNull('deleted_at')
            ->orderBy('username')
            ->get();
    }

    public function get(string $adminUserId): AdminUser
    {
        return AdminUser::query()
            ->where('admin_user_id', $adminUserId)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    public function create(
        string $username,
        string $email,
        string $password,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $mobile = null,
        ?string $createdBy = null
    ): AdminUser {
        return DB::transaction(function () use ($username, $email, $password, $firstName, $lastName, $mobile, $createdBy) {
            if (AdminUser::where('username', $username)->whereNull('deleted_at')->exists()) {
                throw new InvalidArgumentException("Username [{$username}] already exists.");
            }
            if (AdminUser::where('email', $email)->whereNull('deleted_at')->exists()) {
                throw new InvalidArgumentException("Email [{$email}] already exists.");
            }

            $user = AdminUser::create([
                'username'       => $username,
                'email'          => $email,
                'password_hash'  => Hash::make($password),
                'first_name'     => $firstName,
                'last_name'      => $lastName,
                'mobile'         => $mobile,
                'status'         => 1,
                'created_by'     => $createdBy,
                'updated_by'     => $createdBy,
            ]);

            $this->auditLogService->write(
                entityName: 'admin_users',
                actionType: 'CREATE',
                entityId: $user->admin_user_id,
                adminUserId: $createdBy,
                newValues: [
                    'username' => $user->username,
                    'email'    => $user->email,
                    'status'   => $user->status,
                ],
                createdBy: $createdBy
            );

            $this->logEventOutbox(
                'admin_users',
                $user->admin_user_id,
                'SaasAdmin.AdminUserCreated.v1',
                [
                    'admin_user_id' => $user->admin_user_id,
                    'username'      => $user->username,
                    'email'         => $user->email,
                    'status'        => $user->status,
                ]
            );

            return $user;
        });
    }

    public function update(
        string $adminUserId,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $mobile = null,
        ?int $status = null,
        ?string $updatedBy = null
    ): AdminUser {
        return DB::transaction(function () use ($adminUserId, $firstName, $lastName, $mobile, $status, $updatedBy) {
            $user = $this->get($adminUserId);
            $old = $user->only(['first_name', 'last_name', 'mobile', 'status']);

            $changes = array_filter([
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'mobile'     => $mobile,
                'status'     => $status,
            ], fn ($v) => !is_null($v));

            if (!empty($changes)) {
                $changes['row_version'] = ((int) ($user->row_version ?? 1)) + 1;
                $changes['updated_by']  = $updatedBy;
                $user->update($changes);

                $this->auditLogService->write(
                    entityName: 'admin_users',
                    actionType: 'UPDATE',
                    entityId: $adminUserId,
                    adminUserId: $updatedBy,
                    oldValues: $old,
                    newValues: $changes,
                    createdBy: $updatedBy
                );
            }

            $this->logEventOutbox(
                'admin_users',
                $user->admin_user_id,
                'SaasAdmin.AdminUserUpdated.v1',
                [
                    'admin_user_id' => $user->admin_user_id,
                    'changes'       => $changes,
                ]
            );

            return $user->fresh();
        });
    }

    public function softDelete(string $adminUserId, ?string $deletedBy = null): void
    {
        DB::transaction(function () use ($adminUserId, $deletedBy) {
            $user = $this->get($adminUserId);
            $user->deleted_by = $deletedBy;
            $user->save();
            $user->delete();

            $this->auditLogService->write(
                entityName: 'admin_users',
                actionType: 'DELETE',
                entityId: $adminUserId,
                adminUserId: $deletedBy,
                createdBy: $deletedBy
            );

            $this->logEventOutbox(
                'admin_users',
                $adminUserId,
                'SaasAdmin.AdminUserDeleted.v1',
                ['admin_user_id' => $adminUserId]
            );
        });
    }

    private function logEventOutbox(
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload
    ): void {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => '00000000-0000-0000-0000-000000000000',
            'aggregate_type' => $aggregateType,
            'aggregate_id'   => $aggregateId,
            'event_type'     => $eventType,
            'payload'        => json_encode($payload),
            'status'         => 1,
            'created_at'     => now(),
        ]);
    }
}
