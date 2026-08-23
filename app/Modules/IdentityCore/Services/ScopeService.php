<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\DTOs\CreateScopeDTO;
use App\Modules\IdentityCore\DTOs\UpdateScopeDTO;
use App\Modules\IdentityCore\DTOs\AssignScopeToUserDTO;
use App\Modules\IdentityCore\Models\TenantScope;
use App\Modules\IdentityCore\Models\TenantUserScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;
use Exception;

class ScopeService
{
    /**
     * لیست محدوده‌های مستأجر جاری
     */
    public function listScopes(?string $scopeType = null): Collection
    {
        $this->getTenantId();

        $query = TenantScope::query()->orderBy('scope_type')->orderBy('scope_name');

        if ($scopeType) {
            $query->where('scope_type', $scopeType);
        }

        return $query->get();
    }

    /**
     * دریافت یک محدوده
     */
    public function getScope(string $scopeId): TenantScope
    {
        $this->getTenantId();

        return TenantScope::where('scope_id', $scopeId)->firstOrFail();
    }

    /**
     * ایجاد محدوده جدید
     */
    public function createScope(CreateScopeDTO $dto): TenantScope
    {
        $tenantId = $this->getTenantId();

        return DB::transaction(function () use ($dto, $tenantId) {
            $scope = TenantScope::create([
                'tenant_id'    => $tenantId,
                'scope_name'   => $dto->scopeName,
                'scope_type'   => $dto->scopeType,
                'reference_id' => $dto->referenceId,
                'description'  => $dto->description,
                'is_active'    => $dto->isActive,
            ]);

            $this->logEventOutbox(
                $tenantId,
                'tenant_scopes',
                $scope->scope_id,
                'identity.scope.created',
                [
                    'scope_id'   => $scope->scope_id,
                    'scope_name' => $scope->scope_name,
                    'scope_type' => $scope->scope_type,
                ]
            );

            return $scope;
        });
    }

    /**
     * به‌روزرسانی محدوده
     */
    public function updateScope(UpdateScopeDTO $dto): TenantScope
    {
        $tenantId = $this->getTenantId();

        return DB::transaction(function () use ($dto, $tenantId) {
            $scope = TenantScope::where('scope_id', $dto->scopeId)->firstOrFail();

            $updateData = array_filter([
                'scope_name'   => $dto->scopeName,
                'scope_type'   => $dto->scopeType,
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
                'identity.scope.updated',
                [
                    'scope_id' => $scope->scope_id,
                    'changes'  => $updateData,
                ]
            );

            return $scope->fresh();
        });
    }

    /**
     * حذف نرم محدوده
     */
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
                'identity.scope.deleted',
                ['scope_id' => $scopeId]
            );
        });
    }

    /**
     * تخصیص محدوده‌ها به کاربر
     */
    public function assignScopesToUser(AssignScopeToUserDTO $dto): void
    {
        $tenantId = $this->getTenantId();

        DB::transaction(function () use ($dto, $tenantId) {
            // حذف تخصیص‌های قبلی این کاربر (soft delete)
            TenantUserScope::where('tenant_user_id', $dto->tenantUserId)
                ->where('tenant_id', $tenantId)
                ->delete();

            $insertData = [];
            foreach ($dto->scopeIds as $scopeId) {
                // اعتبارسنجی وجود scope
                TenantScope::where('scope_id', $scopeId)
                    ->where('tenant_id', $tenantId)
                    ->firstOrFail();

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
                'identity.scope.assigned',
                [
                    'tenant_user_id' => $dto->tenantUserId,
                    'scope_ids'      => $dto->scopeIds,
                ]
            );
        });
    }

    /**
     * دریافت محدوده‌های تخصیص‌یافته به یک کاربر
     */
    public function getUserScopes(string $tenantUserId): Collection
    {
        $this->getTenantId();

        return TenantUserScope::with('scope')
            ->where('tenant_user_id', $tenantUserId)
            ->get()
            ->pluck('scope')
            ->filter();
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