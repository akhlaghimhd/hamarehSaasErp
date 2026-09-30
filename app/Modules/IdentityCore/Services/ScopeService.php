<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\DTOs\CreateScopeDTO;
use App\Modules\IdentityCore\DTOs\UpdateScopeDTO;
use App\Modules\IdentityCore\DTOs\AssignScopeToUserDTO;
use App\Modules\IdentityCore\Models\TenantScope;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantUserScope;
use App\Modules\SaasPlatform\Services\FeatureCatalogService;
use App\Base\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Exception;

class ScopeService
{
    private const STRUCTURAL_TYPES = [
        'COMPANY',
        'BRANCH',
        'WAREHOUSE',
        'DEPARTMENT',
        'COST_CENTER',
        'BUSINESS_UNIT',
    ];

    public function listScopes(?string $scopeType = null): array
    {
        $tenantId = $this->getTenantId();

        $query = TenantScope::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('scope_name');

        if ($scopeType) {
            $query->where('scope_type', strtoupper($scopeType));
        }

        return $query->get()->all();
    }

    public function getScope(string $scopeId): TenantScope
    {
        $tenantId = $this->getTenantId();

        return TenantScope::where('tenant_id', $tenantId)
            ->where('scope_id', $scopeId)
            ->firstOrFail();
    }

    public function createScope(CreateScopeDTO $dto): TenantScope
    {
        $tenantId = $this->getTenantId();

        $this->assertPackAllowsScopeType($tenantId, $dto->scopeType);
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

            if ($dto->scopeType !== null) {
                $this->assertPackAllowsScopeType($tenantId, $scopeType);
            }

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
                    'scope_id'     => $scope->scope_id,
                    'scope_name'   => $scope->scope_name,
                    'scope_type'   => $scope->scope_type,
                    'reference_id' => $scope->reference_id,
                ]
            );

            return $scope->fresh();
        });
    }

    public function deleteScope(string $scopeId): void
    {
        $tenantId = $this->getTenantId();

        DB::transaction(function () use ($scopeId, $tenantId) {
            $scope = TenantScope::where('tenant_id', $tenantId)
                ->where('scope_id', $scopeId)
                ->firstOrFail();

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

    public function assignScopeToUser(AssignScopeToUserDTO $dto): void
    {
        $tenantId = $this->getTenantId();

        $this->assertTenantUserBelongsToTenant($tenantId, $dto->tenantUserId);

        $scope = TenantScope::where('tenant_id', $tenantId)
            ->where('scope_id', $dto->scopeId)
            ->where('is_active', true)
            ->firstOrFail();

        DB::transaction(function () use ($dto, $tenantId, $scope) {
            $existing = TenantUserScope::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('tenant_user_id', $dto->tenantUserId)
                ->where('scope_id', $dto->scopeId)
                ->first();

            if ($existing) {
                if ($existing->deleted_at) {
                    $existing->restore();
                }
                return;
            }

            TenantUserScope::create([
                'tenant_id'      => $tenantId,
                'tenant_user_id' => $dto->tenantUserId,
                'scope_id'       => $dto->scopeId,
            ]);

            $this->logEventOutbox(
                $tenantId,
                'tenant_user_scopes',
                $dto->tenantUserId,
                'identity.scope.assigned.v1',
                [
                    'tenant_user_id' => $dto->tenantUserId,
                    'scope_id'       => $scope->scope_id,
                    'scope_type'     => $scope->scope_type,
                ]
            );
        });
    }

    public function unassignScopeFromUser(string $tenantUserId, string $scopeId): void
    {
        $tenantId = $this->getTenantId();

        DB::transaction(function () use ($tenantUserId, $scopeId, $tenantId) {
            $row = TenantUserScope::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('tenant_user_id', $tenantUserId)
                ->where('scope_id', $scopeId)
                ->whereNull('deleted_at')
                ->first();

            if (!$row) {
                return;
            }

            $row->delete();

            $this->logEventOutbox(
                $tenantId,
                'tenant_user_scopes',
                $tenantUserId,
                'identity.scope.unassigned.v1',
                [
                    'tenant_user_id' => $tenantUserId,
                    'scope_id'       => $scopeId,
                ]
            );
        });
    }

    public function listScopesForUser(string $tenantUserId): array
    {
        $tenantId = $this->getTenantId();

        return TenantScope::query()
            ->where('tenant_scopes.tenant_id', $tenantId)
            ->whereIn('tenant_scopes.scope_id', function ($q) use ($tenantId, $tenantUserId) {
                $q->select('scope_id')
                    ->from('tenant_user_scopes')
                    ->where('tenant_id', $tenantId)
                    ->where('tenant_user_id', $tenantUserId)
                    ->whereNull('deleted_at');
            })
            ->orderBy('scope_name')
            ->get()
            ->all();
    }

    private function assertPackAllowsScopeType(string $tenantId, string $scopeType): void
    {
        $type = strtoupper($scopeType);
        $features = app(FeatureCatalogService::class);

        if ($type === 'BUSINESS_UNIT') {
            $features->assertEnabled($tenantId, FeatureCatalogService::CODE_MULTI_BUSINESS_UNIT);
        }
    }

    private function assertTenantUserBelongsToTenant(string $tenantId, string $tenantUserId): void
    {
        $tenantUser = TenantUser::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('tenant_user_id', $tenantUserId)
            ->whereNull('deleted_at')
            ->first();

        if (!$tenantUser) {
            throw new Exception('tenant_user_id is invalid for the current tenant.');
        }
    }

    private function assertReferenceValid(string $tenantId, string $scopeType, ?string $referenceId): void
    {
        $type = strtoupper(trim($scopeType));
        $referenceId = is_string($referenceId) ? trim($referenceId) : $referenceId;

        if (in_array($type, self::STRUCTURAL_TYPES, true) && ($referenceId === null || $referenceId === '')) {
            throw new Exception("برای نوع محدوده {$type} انتخاب موجودیت مرجع (reference_id) الزامی است.");
        }

        if ($referenceId === null || $referenceId === '') {
            return;
        }

        try {
            DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);
        } catch (\Throwable) {
            // non-fatal
        }

        $map = [
            'COMPANY' => ['erp_companies', 'company_id'],
            'BRANCH' => ['erp_branches', 'branch_id'],
            'DEPARTMENT' => ['erp_departments', 'department_id'],
            'WAREHOUSE' => ['inv_warehouses', 'warehouse_id'],
            'COST_CENTER' => ['erp_cost_centers', 'cost_center_id'],
            'BUSINESS_UNIT' => ['erp_business_units', 'business_unit_id'],
        ];

        if (!isset($map[$type])) {
            return;
        }

        [$table, $pk] = $map[$type];

        $tables = [$table];
        if ($type === 'COST_CENTER') {
            $tables[] = 'cost_centers';
        }

        $found = false;
        $softDeleted = false;

        foreach ($tables as $tbl) {
            if (! Schema::hasTable($tbl)) {
                continue;
            }
            if (! Schema::hasColumn($tbl, $pk)) {
                continue;
            }

            $active = DB::table($tbl)
                ->where('tenant_id', $tenantId)
                ->where($pk, $referenceId);

            if (Schema::hasColumn($tbl, 'deleted_at')) {
                $active->whereNull('deleted_at');
            }

            if ($active->exists()) {
                $found = true;
                break;
            }

            if (Schema::hasColumn($tbl, 'deleted_at')) {
                $any = DB::table($tbl)
                    ->where('tenant_id', $tenantId)
                    ->where($pk, $referenceId)
                    ->exists();
                if ($any) {
                    $softDeleted = true;
                }
            }
        }

        if ($found) {
            return;
        }

        if ($softDeleted) {
            throw new Exception("موجودیت مرجع برای نوع {$type} حذف شده است و قابل استفاده به‌عنوان محدوده نیست.");
        }

        throw new Exception(
            "موجودیت مرجع انتخاب‌شده برای نوع {$type} در این سازمان یافت نشد. (شناسه: {$referenceId})"
        );
    }

    private function getTenantId(): string
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        if (!$tenantId) {
            throw new Exception('Tenant context is not set.');
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
            if (! Schema::hasTable('event_outbox')) {
                return;
            }

            // Schema: event_id PK, status smallint (1=pending), no updated_at
            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => $aggregateType,
                'aggregate_id'   => $aggregateId,
                'event_type'     => $eventType,
                'payload'        => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);
        } catch (\Throwable) {
            // best-effort — scope mutation must not fail on outbox write
        }
    }
}
