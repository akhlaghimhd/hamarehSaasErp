<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\DTOs\CreateScopeDTO;
use App\Modules\IdentityCore\DTOs\UpdateScopeDTO;
use App\Modules\IdentityCore\DTOs\AssignScopeToUserDTO;
use App\Modules\IdentityCore\Models\TenantScope;
use App\Modules\IdentityCore\Models\TenantUserScope;
use App\Modules\IdentityCore\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;
use Exception;

class ScopeService
{
    /** Scope types that must point at a concrete resource in the same tenant. */
    private const STRUCTURAL_TYPES = [
        'COMPANY',
        'BRANCH',
        'WAREHOUSE',
        'DEPARTMENT',
        'COST_CENTER',
    ];

    public function listScopes(?string $scopeType = null): Collection
    {
        $this->getTenantId();

        $query = TenantScope::query()->orderBy('scope_type')->orderBy('scope_name');

        if ($scopeType) {
            $query->where('scope_type', $scopeType);
        }

        return $query->get();
    }

    public function getScope(string $scopeId): TenantScope
    {
        $this->getTenantId();

        return TenantScope::where('scope_id', $scopeId)->firstOrFail();
    }

    public function createScope(CreateScopeDTO $dto): TenantScope
    {
        $tenantId = $this->getTenantId();

        $this->assertReferenceValid($tenantId, $dto->scopeType, $dto->referenceId);

        return DB::transaction(function () use ($dto, $tenantId) {
            $scope = TenantScope::create([
                'tenant_id'    => $tenantId,
                'scope_name'   => $dto->scopeName,
                'scope_type'   => strtoupper($dto->scopeType),
                'reference_id' => $dto->referenceId,
                'description'  => $dto->description,
                'is_active'    => $dto->isActive,
            ]);

            $this->logEventOutbox(
                $tenantId,
                'tenant_scopes',
                $scope->scope_id,
                'identity.scope.created.v1',
                [
                    'scope_id'     => $scope->scope_id,
                    'scope_name'   => $scope->scope_name,
                    'scope_type'   => $scope->scope_type,
                    'reference_id' => $scope->reference_id,
                ]
            );

            return $scope;
        });
    }

    public function updateScope(UpdateScopeDTO $dto): TenantScope
    {
        $tenantId = $this->getTenantId();

        return DB::transaction(function () use ($dto, $tenantId) {
            $scope = TenantScope::where('scope_id', $dto->scopeId)->firstOrFail();

            $scopeType = $dto->scopeType !== null ? strtoupper($dto->scopeType) : $scope->scope_type;
            $referenceId = $dto->referenceId !== null ? $dto->referenceId : $scope->reference_id;

            $this->assertReferenceValid($tenantId, $scopeType, $referenceId);

            $updateData = array_filter([
                'scope_name'   => $dto->scopeName,
                'scope_type'   => $dto->scopeType !== null ? strtoupper($dto->scopeType) : null,
                'reference_id' => $dto->referenceId,
                'description'  => $dto->description,
                'is_active'    => $dto->isActive,
            ], fn ($value) => !is_null($value));

            if (!empty($updateData)) {
                $scope->update($updateData);
            }

            $this->logEventOutbox(
                $tenantId,
                'tenant_scopes',
                $scope->scope_id,
                'identity.scope.updated.v1',
                [
                    'scope_id' => $scope->scope_id,
                    'changes'  => $updateData,
                ]
            );

            return $scope->fresh();
        });
    }

    public function deleteScope(string $scopeId): void
    {
        $tenantId = $this->getTenantId();

        DB::transaction(function () use ($scopeId, $tenantId) {
            $scope = TenantScope::where('scope_id', $scopeId)->firstOrFail();
            $scope->delete();

            $this->logEventOutbox(
                $tenantId,
                'tenant_scopes',
                $scopeId,
                'identity.scope.deleted.v1',
                ['scope_id' => $scopeId]
            );
        });
    }

    public function assignScopesToUser(AssignScopeToUserDTO $dto): void
    {
        $tenantId = $this->getTenantId();

        DB::transaction(function () use ($dto, $tenantId) {
            $tenantUser = TenantUser::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('tenant_user_id', $dto->tenantUserId)
                ->whereNull('deleted_at')
                ->first();

            if (!$tenantUser) {
                throw new Exception('tenant_user_id is invalid for the current tenant.');
            }

            TenantUserScope::where('tenant_user_id', $dto->tenantUserId)
                ->where('tenant_id', $tenantId)
                ->delete();

            $insertData = [];
            foreach ($dto->scopeIds as $scopeId) {
                $scope = TenantScope::where('scope_id', $scopeId)
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->first();

                if (!$scope) {
                    throw new Exception("Scope {$scopeId} is invalid or inactive for this tenant.");
                }

                $insertData[] = [
                    'assignment_id'  => (string) Str::uuid(),
                    'tenant_id'      => $tenantId,
                    'tenant_user_id' => $dto->tenantUserId,
                    'scope_id'       => $scopeId,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                    'row_version'    => 1,
                ];
            }

            if (!empty($insertData)) {
                TenantUserScope::insert($insertData);
            }

            $this->logEventOutbox(
                $tenantId,
                'tenant_user_scopes',
                $dto->tenantUserId,
                'identity.scope.assigned.v1',
                [
                    'tenant_user_id' => $dto->tenantUserId,
                    'scope_ids'      => $dto->scopeIds,
                ]
            );
        });
    }

    public function getUserScopes(string $tenantUserId): Collection
    {
        $this->getTenantId();

        return TenantUserScope::with('scope')
            ->where('tenant_user_id', $tenantUserId)
            ->get()
            ->pluck('scope')
            ->filter()
            ->values();
    }

    /**
     * Logical reference check (no cross-module physical FK).
     */
    private function assertReferenceValid(string $tenantId, string $scopeType, ?string $referenceId): void
    {
        $type = strtoupper($scopeType);

        if (in_array($type, self::STRUCTURAL_TYPES, true) && empty($referenceId)) {
            throw new Exception("reference_id is required for scope_type {$type}.");
        }

        if (empty($referenceId)) {
            return;
        }

        $exists = match ($type) {
            'COMPANY' => DB::table('erp_companies')
                ->where('tenant_id', $tenantId)
                ->where('company_id', $referenceId)
                ->whereNull('deleted_at')
                ->exists(),
            'BRANCH' => DB::table('erp_branches')
                ->where('tenant_id', $tenantId)
                ->where('branch_id', $referenceId)
                ->whereNull('deleted_at')
                ->exists(),
            'DEPARTMENT' => DB::table('erp_departments')
                ->where('tenant_id', $tenantId)
                ->where('department_id', $referenceId)
                ->whereNull('deleted_at')
                ->exists(),
            'WAREHOUSE' => DB::table('warehouses')
                ->where('tenant_id', $tenantId)
                ->where('warehouse_id', $referenceId)
                ->whereNull('deleted_at')
                ->exists(),
            'COST_CENTER' => DB::table('cost_centers')
                ->where('tenant_id', $tenantId)
                ->where('cost_center_id', $referenceId)
                ->whereNull('deleted_at')
                ->exists(),
            default => true,
        };

        if (!$exists) {
            throw new Exception("reference_id does not exist for scope_type {$type} in this tenant.");
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
    }
}
