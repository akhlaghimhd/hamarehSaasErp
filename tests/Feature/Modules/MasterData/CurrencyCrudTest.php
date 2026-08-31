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
use App\Modules\MasterData\Models\Currency;
use App\Base\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L5-MD-P01 — Currency (Platform Master Data) CRUD + permission + unique code
 */
class CurrencyCrudTest extends TestCase
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
            'tenant_code' => 'CUR_CRUD',
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
            'code'      => 'currency-manager',
            'name'      => 'Currency Manager',
            'status'    => 1,
        ]);

        foreach ([
            'master-data.currency.view',
            'master-data.currency.create',
            'master-data.currency.update',
            'master-data.currency.delete',
        ] as $code) {
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
            'user_id'             => $this->authorizedUser->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        $this->authorizedToken = $this->authorizedUser->createToken(
            'cur-auth',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $this->unauthorizedUser = User::factory()->create(['status' => 1]);
        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->unauthorizedUser->user_id,
            'status'    => 1,
        ]);
        $this->unauthorizedToken = $this->unauthorizedUser->createToken(
            'cur-unauth',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);
    }

    protected function tearDown(): void
    {
        TenantContext::resetInstance();
        parent::tearDown();
    }

    protected function authHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ];
    }

    #[Test]
    public function can_create_list_show_update_and_delete_currency(): void
    {
        $create = $this->withHeaders($this->authHeaders($this->authorizedToken))
            ->postJson('/api/master-data/currencies', [
                'code'       => 'IRR',
                'name'       => 'Iranian Rial',
                'symbol'     => '﷼',
                'is_default' => true,
                'status'     => true,
            ]);

        $create->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.code', 'IRR');

        $currencyId = $create->json('data.currency_id');
        $this->assertNotEmpty($currencyId);

        $this->assertDatabaseHas('currencies', [
            'currency_id' => $currencyId,
            'code'        => 'IRR',
            'is_default'  => true,
        ]);

        $index = $this->withHeaders($this->authHeaders($this->authorizedToken))
            ->getJson('/api/master-data/currencies');
        $index->assertStatus(200);
        $codes = collect($index->json('data'))->pluck('code')->toArray();
        $this->assertContains('IRR', $codes);

        $show = $this->withHeaders($this->authHeaders($this->authorizedToken))
            ->getJson('/api/master-data/currencies/' . $currencyId);
        $show->assertStatus(200)->assertJsonPath('data.currency_id', $currencyId);

        $update = $this->withHeaders($this->authHeaders($this->authorizedToken))
            ->putJson('/api/master-data/currencies/' . $currencyId, [
                'name'   => 'Iranian Rial Updated',
                'status' => false,
            ]);
        $update->assertStatus(200)
            ->assertJsonPath('data.name', 'Iranian Rial Updated')
            ->assertJsonPath('data.status', false);

        $delete = $this->withHeaders($this->authHeaders($this->authorizedToken))
            ->deleteJson('/api/master-data/currencies/' . $currencyId);
        $delete->assertStatus(200);

        $this->assertDatabaseMissing('currencies', [
            'currency_id' => $currencyId,
        ]);
    }

    #[Test]
    public function currency_code_must_be_unique(): void
    {
        $this->withHeaders($this->authHeaders($this->authorizedToken))
            ->postJson('/api/master-data/currencies', [
                'code' => 'USD',
                'name' => 'US Dollar',
            ])
            ->assertStatus(201);

        $dup = $this->withHeaders($this->authHeaders($this->authorizedToken))
            ->postJson('/api/master-data/currencies', [
                'code' => 'USD',
                'name' => 'Another Dollar',
            ]);

        $this->assertNotEquals(201, $dup->status());
    }

    #[Test]
    public function unauthorized_user_cannot_create_currency(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->unauthorizedToken))
            ->postJson('/api/master-data/currencies', [
                'code' => 'EUR',
                'name' => 'Euro',
            ]);

        $this->assertNotEquals(201, $response->status());
        $this->assertDatabaseMissing('currencies', ['code' => 'EUR']);
    }

    #[Test]
    public function setting_default_clears_previous_default(): void
    {
        $first = $this->withHeaders($this->authHeaders($this->authorizedToken))
            ->postJson('/api/master-data/currencies', [
                'code'       => 'USD',
                'name'       => 'US Dollar',
                'is_default' => true,
            ]);
        $first->assertStatus(201);
        $firstId = $first->json('data.currency_id');

        $second = $this->withHeaders($this->authHeaders($this->authorizedToken))
            ->postJson('/api/master-data/currencies', [
                'code'       => 'EUR',
                'name'       => 'Euro',
                'is_default' => true,
            ]);
        $second->assertStatus(201);
        $secondId = $second->json('data.currency_id');

        $this->assertDatabaseHas('currencies', [
            'currency_id' => $firstId,
            'is_default'  => false,
        ]);
        $this->assertDatabaseHas('currencies', [
            'currency_id' => $secondId,
            'is_default'  => true,
        ]);
    }
}
