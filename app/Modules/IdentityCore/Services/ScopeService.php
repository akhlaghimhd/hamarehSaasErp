<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\DTOs\CreateScopeDTO;
use App\Modules\IdentityCore\DTOs\UpdateScopeDTO;
use App\Modules\IdentityCore\DTOs\AssignScopeToUserDTO;
use App\Modules\IdentityCore\Models\TenantScope;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantUserScope;
use App\Modules\IdentityCore\Models\TenantScopeMember;
use App\Modules\SaasPlatform\Services\FeatureCatalogService;
use App\Base\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Base\Exceptions\DomainException;
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

    public function listScopes(?string $scopeType = null, bool $onlyTrashed = false): array
    {
        $tenantId = $this->getTenantId();

        $query = TenantScope::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('scope_name');

        if ($onlyTrashed) {
            $query->onlyTrashed();
        }

        if ($scopeType) {
            $query->where('scope_type', strtoupper($scopeType));
        }

        $scopes = $query->get()->all();
        $scopes = $this->hydrateReferenceIds($scopes);

        return array_map(static function (TenantScope $s): array {
            $row = $s->toArray();
            $refs = $s->getAttribute('reference_ids');
            $row['reference_ids'] = is_array($refs) ? array_values($refs) : [];
            if (empty($row['reference_ids']) && !empty($row['reference_id'])) {
                $row['reference_ids'] = [(string) $row['reference_id']];
            }

            return $row;
        }, $scopes);
    }

    public function getScope(string $scopeId): TenantScope
    {
        $tenantId = $this->getTenantId();

        $scope = TenantScope::where('tenant_id', $tenantId)
            ->where('scope_id', $scopeId)
            ->firstOrFail();

        $hydrated = $this->hydrateReferenceIds([$scope]);

        return $hydrated[0];
    }

    public function createScope(CreateScopeDTO $dto): TenantScope
    {
        $tenantId = $this->getTenantId();
        $type = strtoupper($dto->scopeType);

        $this->assertPackAllowsScopeType($tenantId, $type);

        $referenceIds = array_values(array_unique(array_filter($dto->referenceIds)));
        if (in_array($type, self::STRUCTURAL_TYPES, true) && $referenceIds === []) {
            throw new DomainException(
                'برای محدوده ساختاری باید حداقل یک موجودیت مرجع هم‌نوع انتخاب شود.',
                'scope_reference_required'
            );
        }

        foreach ($referenceIds as $rid) {
            $this->assertReferenceValid($tenantId, $type, $rid);
        }

        return DB::transaction(function () use ($dto, $tenantId, $type, $referenceIds) {
            $primary = $referenceIds[0] ?? null;

            $scope = TenantScope::create([
                'tenant_id'    => $tenantId,
                'scope_name'   => $dto->scopeName,
                'scope_type'   => $type,
                'reference_id' => $primary,
                'description'  => $dto->description,
                'is_active'    => $dto->isActive,
            ]);

            $this->syncScopeMembers($tenantId, $scope->scope_id, $referenceIds);

            $this->logEventOutbox(
                $tenantId,
                'tenant_scopes',
                $scope->scope_id,
                'identity.scope.created.v1',
                [
                    'scope_id'      => $scope->scope_id,
                    'scope_name'    => $scope->scope_name,
                    'scope_type'    => $scope->scope_type,
                    'reference_id'  => $primary,
                    'reference_ids' => $referenceIds,
                ]
            );

            $hydrated = $this->hydrateReferenceIds([$scope->fresh()]);

            return $hydrated[0];
        });
    }

    public function updateScope(UpdateScopeDTO $dto): TenantScope
    {
        $tenantId = $this->getTenantId();

        return DB::transaction(function () use ($dto, $tenantId) {
            $scope = TenantScope::where('tenant_id', $tenantId)
                ->where('scope_id', $dto->scopeId)
                ->firstOrFail();

            $scopeType = $dto->scopeType !== null ? strtoupper($dto->scopeType) : $scope->scope_type;

            if ($dto->scopeType !== null) {
                $this->assertPackAllowsScopeType($tenantId, $scopeType);
            }

            $referenceIds = $dto->referenceIds;
            if ($referenceIds !== null) {
                $referenceIds = array_values(array_unique(array_filter($referenceIds)));
                if (in_array(strtoupper($scopeType), self::STRUCTURAL_TYPES, true) && $referenceIds === []) {
                    throw new DomainException(
                        'برای محدوده ساختاری باید حداقل یک موجودیت مرجع هم‌نوع انتخاب شود.',
                        'scope_reference_required'
                    );
                }
                foreach ($referenceIds as $rid) {
                    $this->assertReferenceValid($tenantId, $scopeType, $rid);
                }
            }

            $updateData = array_filter([
                'scope_name'  => $dto->scopeName,
                'scope_type'  => $dto->scopeType !== null ? strtoupper($dto->scopeType) : null,
                'description' => $dto->description,
                'is_active'   => $dto->isActive,
            ], fn ($value) => !is_null($value));

            if ($referenceIds !== null) {
                $updateData['reference_id'] = $referenceIds[0] ?? null;
            }

            if (!empty($updateData)) {
                $scope->update($updateData);
            }

            if ($referenceIds !== null) {
                $this->syncScopeMembers($tenantId, $scope->scope_id, $referenceIds);
            }

            $this->logEventOutbox(
                $tenantId,
                'tenant_scopes',
                $scope->scope_id,
                'identity.scope.updated.v1',
                [
                    'scope_id'      => $scope->scope_id,
                    'scope_name'    => $scope->scope_name,
                    'scope_type'    => $scope->scope_type,
                    'reference_id'  => $scope->reference_id,
                    'reference_ids' => $referenceIds,
                ]
            );

            $hydrated = $this->hydrateReferenceIds([$scope->fresh()]);

            return $hydrated[0];
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

    public function restoreScope(string $scopeId): TenantScope
    {
        $tenantId = $this->getTenantId();

        return DB::transaction(function () use ($scopeId, $tenantId) {
            $scope = TenantScope::onlyTrashed()
                ->where('tenant_id', $tenantId)
                ->where('scope_id', $scopeId)
                ->firstOrFail();

            $scope->restore();

            $this->logEventOutbox(
                $tenantId,
                'tenant_scopes',
                $scopeId,
                'identity.scope.restored.v1',
                ['scope_id' => $scopeId]
            );

            return $scope->fresh();
        });
    }

    public function assignScopeToUser(AssignScopeToUserDTO $dto): void
    {
        $tenantId = $this->getTenantId();

        $this->assertTenantUserBelongsToTenant($tenantId, $dto->tenantUserId);

        foreach ($dto->scopeIds as $scopeId) {
            $scope = TenantScope::where('tenant_id', $tenantId)
                ->where('scope_id', $scopeId)
                ->where('is_active', true)
                ->firstOrFail();

            DB::transaction(function () use ($dto, $tenantId, $scope, $scopeId) {
                $existing = TenantUserScope::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('tenant_user_id', $dto->tenantUserId)
                    ->where('scope_id', $scopeId)
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
                    'scope_id'       => $scopeId,
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

        $scopes = TenantScope::query()
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

        return $this->hydrateReferenceIds($scopes);
    }

    private function assertReferenceNotDuplicate(
        string $tenantId,
        string $scopeType,
        ?string $referenceId,
        ?string $exceptScopeId = null
    ): void {
        return;
    }

    private function syncScopeMembers(string $tenantId, string $scopeId, array $referenceIds): void
    {
        $referenceIds = array_values(array_unique(array_filter($referenceIds)));

        if (! Schema::hasTable('tenant_scope_members')) {
            return;
        }

        try {
            $existing = TenantScopeMember::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('scope_id', $scopeId)
                ->get();
        } catch (\Throwable $e) {
            report($e);
            foreach ($referenceIds as $rid) {
                try {
                    DB::table('tenant_scope_members')->insertOrIgnore([
                        'scope_member_id' => (string) Str::uuid(),
                        'tenant_id'       => $tenantId,
                        'scope_id'        => $scopeId,
                        'reference_id'    => $rid,
                        'created_at'      => now(),
                        'row_version'     => 1,
                    ]);
                } catch (\Throwable) {
                    // ignore
                }
            }

            return;
        }

        $keep = [];
        foreach ($existing as $row) {
            $rid = (string) $row->reference_id;
            if (in_array($rid, $referenceIds, true)) {
                if ($row->trashed()) {
                    $row->restore();
                }
                $keep[] = $rid;
            } else {
                if (!$row->trashed()) {
                    $row->delete();
                }
            }
        }

        foreach ($referenceIds as $rid) {
            if (in_array($rid, $keep, true)) {
                continue;
            }
            TenantScopeMember::create([
                'tenant_id'    => $tenantId,
                'scope_id'     => $scopeId,
                'reference_id' => $rid,
            ]);
        }
    }

    /**
     * @param  list<TenantScope>  $scopes
     * @return list<TenantScope>
     */
    private function hydrateReferenceIds(array $scopes): array
    {
        if ($scopes === []) {
            return [];
        }

        $ids = [];
        foreach ($scopes as $s) {
            $ids[] = (string) $s->scope_id;
        }

        $grouped = [];
        try {
            if ($ids !== [] && Schema::hasTable('tenant_scope_members')) {
                $rows = DB::table('tenant_scope_members')
                    ->whereIn('scope_id', $ids)
                    ->whereNull('deleted_at')
                    ->orderBy('created_at')
                    ->get(['scope_id', 'reference_id']);

                foreach ($rows as $row) {
                    $grouped[(string) $row->scope_id][] = (string) $row->reference_id;
                }
            }
        } catch (\Throwable $e) {
            report($e);
            $grouped = [];
        }

        foreach ($scopes as $s) {
            $sid = (string) $s->scope_id;
            $refs = $grouped[$sid] ?? [];
            if ($refs === [] && !empty($s->reference_id)) {
                $refs = [(string) $s->reference_id];
            }
            $refs = array_values(array_unique(array_filter($refs)));
            $s->setAttribute('reference_ids', $refs);
            if ($refs !== [] && empty($s->reference_id)) {
                $s->setAttribute('reference_id', $refs[0]);
            }
        }

        return $scopes;
    }

    private function assertTenantUserBelongsToTenant(string $tenantId, string $tenantUserId): void
    {
        $exists = TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->where('tenant_user_id', $tenantUserId)
            ->whereNull('deleted_at')
            ->exists();

        if (!$exists) {
            throw new Exception('کاربر عضویت در این سازمان یافت نشد.');
        }
    }

    private function assertPackAllowsScopeType(string $tenantId, string $scopeType): void
    {
        $type = strtoupper(trim($scopeType));
        if ($type === 'CUSTOM' || $type === '') {
            return;
        }

        $packMap = [
            'COMPANY'       => 'multi_company',
            'BRANCH'        => 'multi_branch',
            'BUSINESS_UNIT' => 'multi_business_unit',
        ];

        if (!isset($packMap[$type])) {
            return;
        }

        try {
            $catalog = app(FeatureCatalogService::class);
            if (method_exists($catalog, 'tenantHasFeature') && !$catalog->tenantHasFeature($tenantId, $packMap[$type])) {
                throw new DomainException(
                    "برای تعریف محدوده از نوع {$type} باید بسته ویژگی مربوط فعال باشد.",
                    'scope_feature_pack_required'
                );
            }
        } catch (DomainException $e) {
            throw $e;
        } catch (\Throwable) {
            // feature catalog optional
        }
    }

    private function assertReferenceValid(string $tenantId, string $scopeType, ?string $referenceId): void
    {
        if ($referenceId === null || trim((string) $referenceId) === '') {
            return;
        }

        $type = strtoupper(trim($scopeType));
        if (!in_array($type, self::STRUCTURAL_TYPES, true)) {
            return;
        }

        $pkMap = [
            'COMPANY'       => ['erp_companies', 'company_id'],
            'BRANCH'        => ['erp_branches', 'branch_id'],
            'WAREHOUSE'     => ['inv_warehouses', 'warehouse_id'],
            'DEPARTMENT'    => ['erp_departments', 'department_id'],
            'COST_CENTER'   => ['erp_cost_centers', 'cost_center_id'],
            'BUSINESS_UNIT' => ['erp_business_units', 'business_unit_id'],
        ];

        if (!isset($pkMap[$type])) {
            return;
        }

        [$table, $pk] = $pkMap[$type];
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
            // best-effort
        }
    }
}
