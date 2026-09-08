<?php

namespace App\Modules\SaasAdmin\Services;

use App\Modules\SaasAdmin\Models\AdminRole;
use App\Modules\SaasAdmin\Models\AdminPermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class AdminRoleService
{
    public function list(): Collection
    {
        return AdminRole::query()
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->get();
    }

    public function get(string $adminRoleId): AdminRole
    {
        return AdminRole::query()
            ->where('admin_role_id', $adminRoleId)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    public function create(
        string $code,
        string $name,
        ?string $description = null,
        ?string $createdBy = null
    ): AdminRole {
        return DB::transaction(function () use ($code, $name, $description, $createdBy) {
            if (AdminRole::where('code', $code)->whereNull('deleted_at')->exists()) {
                throw new InvalidArgumentException("Role code [{$code}] already exists.");
            }

            $role = AdminRole::create([
                'code'        => $code,
                'name'        => $name,
                'description' => $description,
                'status'      => 1,
                'created_by'  => $createdBy,
                'updated_by'  => $createdBy,
            ]);

            $this->logEventOutbox(
                'admin_roles',
                $role->admin_role_id,
                'SaasAdmin.AdminRoleCreated.v1',
                [
                    'admin_role_id' => $role->admin_role_id,
                    'code'          => $role->code,
                    'name'          => $role->name,
                ]
            );

            return $role;
        });
    }

    public function update(
        string $adminRoleId,
        ?string $name = null,
        ?string $description = null,
        ?int $status = null,
        ?string $updatedBy = null
    ): AdminRole {
        return DB::transaction(function () use ($adminRoleId, $name, $description, $status, $updatedBy) {
            $role = $this->get($adminRoleId);

            $changes = array_filter([
                'name'        => $name,
                'description' => $description,
                'status'      => $status,
            ], fn ($v) => !is_null($v));

            if (!empty($changes)) {
                $changes['row_version'] = ((int) ($role->row_version ?? 1)) + 1;
                $changes['updated_by']  = $updatedBy;
                $role->update($changes);
            }

            $this->logEventOutbox(
                'admin_roles',
                $role->admin_role_id,
                'SaasAdmin.AdminRoleUpdated.v1',
                [
                    'admin_role_id' => $role->admin_role_id,
                    'changes'       => $changes,
                ]
            );

            return $role->fresh();
        });
    }

    public function softDelete(string $adminRoleId, ?string $deletedBy = null): void
    {
        DB::transaction(function () use ($adminRoleId, $deletedBy) {
            $role = $this->get($adminRoleId);
            $role->deleted_by = $deletedBy;
            $role->save();
            $role->delete();

            $this->logEventOutbox(
                'admin_roles',
                $adminRoleId,
                'SaasAdmin.AdminRoleDeleted.v1',
                ['admin_role_id' => $adminRoleId]
            );
        });
    }

    public function assignPermissions(string $adminRoleId, array $permissionIds, ?string $updatedBy = null): AdminRole
    {
        return DB::transaction(function () use ($adminRoleId, $permissionIds, $updatedBy) {
            $role = $this->get($adminRoleId);

            // Soft-delete existing pivots
            DB::table('admin_role_permissions')
                ->where('admin_role_id', $adminRoleId)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => now(),
                    'deleted_by' => $updatedBy,
                    'updated_at' => now(),
                ]);

            foreach ($permissionIds as $permissionId) {
                $exists = AdminPermission::where('admin_permission_id', $permissionId)
                    ->whereNull('deleted_at')
                    ->exists();
                if (!$exists) {
                    throw new InvalidArgumentException("Permission [{$permissionId}] not found.");
                }

                DB::table('admin_role_permissions')->insert([
                    'admin_role_permission_id' => Str::uuid()->toString(),
                    'admin_role_id'            => $adminRoleId,
                    'admin_permission_id'      => $permissionId,
                    'created_at'               => now(),
                    'updated_at'               => now(),
                    'created_by'               => $updatedBy,
                    'updated_by'               => $updatedBy,
                    'row_version'              => 1,
                ]);
            }

            $this->logEventOutbox(
                'admin_roles',
                $adminRoleId,
                'SaasAdmin.AdminRolePermissionsUpdated.v1',
                [
                    'admin_role_id'  => $adminRoleId,
                    'permission_ids' => $permissionIds,
                ]
            );

            return $role->fresh('permissions');
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
