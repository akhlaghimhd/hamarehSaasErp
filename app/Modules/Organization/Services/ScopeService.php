
<?php

namespace App\Modules\Organization\Services;

use App\Modules\IdentityCore\Models\Scope;
use App\Modules\IdentityCore\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScopeService
{
    public function assignScope(string $tenantId, string $userId, string $scopeCode, string $name, string $resource, array $limits = []): Scope
    {
        return DB::transaction(function () use ($tenantId, $userId, $scopeCode, $name, $resource, $limits) {
            $scope = Scope::create([
                'scope_id'    => (string) Str::uuid(),
                'tenant_id'   => $tenantId,
                'code'        => $scopeCode,
                'name'        => $name,
                'resource'    => $resource,
                'limits'      => json_encode($limits),
                'status'      => 1,
                'created_by'  => $userId,
            ]);

            return $scope;
        });
    }

    public function createScope(string $tenantId, string $userId, array $data): Scope
    {
        return $this->assignScope($tenantId, $userId, $data['code'], $data['name'], $data['resource'], $data['limits'] ?? []);
    }

    public function updateScope(Scope $scope, array $data, string $userId): Scope
    {
        $scope->update([
            'name'       => $data['name'] ?? $scope->name,
            'resource'   => $data['resource'] ?? $scope->resource,
            'limits'     => $data['limits'] ?? $scope->limits,
            'updated_by' => $userId,
        ]);

        return $scope;
    }

    public function deleteScope(Scope $scope, string $userId): void
    {
        $scope->update([
            'status'      => 0,
            'deleted_by'  => $userId,
            'deleted_at'  => now(),
        ]);
    }
}