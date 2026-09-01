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
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * Layer 5 – Platform Master Data: Country CRUD (L5-MD-P02)
 */
class CountryCrudTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'COUNTRY_TEST',
            'status'      => 1,
        ]);

        $this->user = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->user->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'country-admin',
            'name'      => 'Country Admin',
            'status'    => 1,
        ]);

        $permissionCodes = [
            'master-data.country.view',
            'master-data.country.create',
            'master-data.country.update',
            'master-data.country.delete',
        ];

        foreach ($permissionCodes as $code) {
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenant->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'MasterData',
                'action_type'          => strtoupper(explode('.', $code)[2] ?? 'VIEW'),
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
            'user_id'             => $this->user->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        $this->token = $this->user->createToken(
            'country-test',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);
        ScopeContext::resetInstance();
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ];
    }

    #[Test]
    public function can_create_list_show_update_and_soft_delete_country(): void
    {
        $createPayload = [
            'iso_code' => 'IR',
            'name'     => 'Iran',
            'phone_code' => '+98',
            'status'   => 1,
        ];

        $createResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/master-data/countries', $createPayload);

        $createResponse->assertStatus(201);

        $countryId = $createResponse->json('data.country_id');
        $this->assertNotEmpty($countryId);

        $this->assertDatabaseHas('countries', [
            'country_id' => $countryId,
            'iso_code'   => 'IR',
            'name'       => 'Iran',
        ]);

        $showResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/master-data/countries/' . $countryId);

        $showResponse->assertStatus(200)
            ->assertJsonPath('data.country_id', $countryId);

        $indexResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/master-data/countries');

        $indexResponse->assertStatus(200);

        $updateResponse = $this->withHeaders($this->authHeaders())
            ->putJson('/api/master-data/countries/' . $countryId, [
                'name' => 'Islamic Republic of Iran',
                'status' => 1,
            ]);

        $updateResponse->assertStatus(200);

        $deleteResponse = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/master-data/countries/' . $countryId);

        $deleteResponse->assertStatus(200);

        $this->assertSoftDeleted('countries', [
            'country_id' => $countryId,
        ]);
    }
}
