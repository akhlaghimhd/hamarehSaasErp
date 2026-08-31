<?php

namespace Tests\Feature\Modules\MasterData;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Base\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class MasterDataPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $authorizedUser;
    protected User $unauthorizedUser;
    protected string $authorizedToken;
    protected string $unauthorizedToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'MD_PERM',
            'status'      => 1,
        ]);

        $this->authorizedUser = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->authorizedUser->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'md-manager',
            'name'      => 'MasterData Manager',
            'status'    => 1,
        ]);

        $permissionCodes = [
            'master-data.business-partner.create',
            'master-data.item.create',
            'master-data.warehouse.create',
        ];

        foreach ($permissionCodes as $code) {
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenant->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'MasterData',
                'action_type'          => 'CREATE',
                'status'               => 1,
            ]);

            TenantRolePermission::create([
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id'                 => $this->tenant->tenant_id,
                'tenant_role_id'            => $role->tenant_role_id,
                'tenant_permission_id'      => $perm->tenant_permission_id,
            ]);
        }

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenant->tenant_id,
            'user_id'             => $this->authorizedUser->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        $this->authorizedToken = $this->authorizedUser->createToken(
            'md-auth-token',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $this->unauthorizedUser = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->unauthorizedUser->user_id,
            'status'    => 1,
        ]);

        $this->unauthorizedToken = $this->unauthorizedUser->createToken(
            'md-unauth-token',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);
    }

    #[Test]
    public function authorized_user_can_create_business_partner(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->authorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/master-data/business-partners', [
            'code'         => 'BP-PERM-001',
            'display_name' => 'Partner With Permission',
            'partner_type' => 2,
            'status'       => 1,
        ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function unauthorized_user_cannot_create_business_partner(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->unauthorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/master-data/business-partners', [
            'code'         => 'BP-FORB',
            'display_name' => 'Forbidden Partner',
            'partner_type' => 2,
            'status'       => 1,
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function unauthorized_user_cannot_create_item(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->unauthorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/master-data/items', [
            'code'      => 'ITEM-FORB',
            'name'      => 'Forbidden Item',
            'item_type' => 1,
            'base_uom'  => 'PCS',
            'status'    => 1,
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function unauthorized_user_cannot_create_warehouse(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->unauthorizedToken,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ])->postJson('/api/master-data/warehouses', [
            'code'      => 'WH-FORB',
            'name'      => 'Forbidden Warehouse',
            'location'  => 'Nowhere',
            'is_active' => true,
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function unauthenticated_request_is_rejected(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-ID' => $this->tenant->tenant_id,
            'Accept'      => 'application/json',
        ])->postJson('/api/master-data/business-partners', [
            'code'         => 'BP-NOAUTH',
            'display_name' => 'No Auth Partner',
            'partner_type' => 2,
            'status'       => 1,
        ]);

        $response->assertStatus(401);
    }
}
