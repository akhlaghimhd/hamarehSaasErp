<?php

namespace App\Modules\SaasPlatform\Services;

use App\Modules\SaasPlatform\DTOs\CreateTenantDTO;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantService
{
    /**
     * Create a new tenant and assign the creating user as owner.
     * Uses IdentityCore TenantUser model (owner of tenant_users table).
     */
    public function createTenant(CreateTenantDTO $dto, string $userId): Tenant
    {
        return DB::transaction(function () use ($dto, $userId) {
            $tenant = Tenant::create([
                'tenant_id'              => (string) Str::uuid(),
                'tenant_code'            => $dto->tenantCode,
                'tenant_name'            => $dto->tenantName,
                'legal_name'             => $dto->legalName,
                'tenant_type'            => $dto->tenantType,
                'slug'                   => $dto->slug,
                'primary_domain_enabled' => false,
                'domain_status'          => 1,
                'status'                 => 1,
                'created_by'             => $userId,
            ]);

            // Logical reference only – no physical FK between modules
            TenantUser::create([
                'tenant_user_id' => (string) Str::uuid(),
                'tenant_id'      => $tenant->tenant_id,
                'user_id'        => $userId,
                'is_owner'       => true,
                'status'         => 1,
                'created_by'     => $userId,
            ]);

            // TODO: Publish TenantCreated.v1 event to Outbox for base data seeding

            return $tenant;
        });
    }
}