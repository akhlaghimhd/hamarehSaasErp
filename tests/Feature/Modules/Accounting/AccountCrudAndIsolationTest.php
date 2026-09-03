<?php

namespace Tests\Feature\Modules\Accounting;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\Accounting\Models\Account;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-ACC-02.1 — Account (Chart of Accounts) full CRUD + tenant isolation + soft-delete + row_version
 */
class AccountCrudAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected string $tokenA;
    protected string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'ACC_A',
            'status'      => 1,
        ]);
        $this->tenantB = Tenant::factory()->create([
            'tenant_code' => 'ACC_B',
            'status'      => 1,
        ]);

        $this->userA = User::factory()->create(['status' => 1]);
        $this->userB = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'user_id'   => $this->userA->user_id,
            'status'    => 1,
        ]);
        TenantUser::factory()->create([
            'tenant_id' => $this->tenantB->tenant_id,
            'user_id'   => $this->userB->user_id,
            'status'    => 1,
        ]);

        $roleA = TenantRole::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'code'      => 'acc-full',
            'name'      => 'Accounting Full',
            'status'    => 1,
        ]);
        $roleB = TenantRole::factory()->create([
            'tenant_id' => $this->tenantB->tenant_id,
            'code'      => 'acc-full',
            'name'      => 'Accounting Full',
            'status'    => 1,
        ]);

        $permissionCodes = [
            'accounting.account.view',
            'accounting.account.create',
            'accounting.account.update',
            'accounting.account.delete',
        ];

        foreach ([$this->tenantA, $this->tenantB] as $tenant) {
            $role = $tenant->tenant_id === $this->tenantA->tenant_id ? $roleA : $roleB;
            $user = $tenant->tenant_id === $this->tenantA->tenant_id ? $this->userA : $this->userB;

            foreach ($permissionCodes as $code) {
                $perm = TenantPermission::create([
                    'tenant_permission_id' => (string) Str::uuid(),
                    'tenant_id'            => $tenant->tenant_id,
                    'code'                 => $code,
                    'name'                 => $code,
                    'module_name'          => 'Accounting',
                    'action_type'          => strtoupper(explode('.', $code)[2] ?? 'VIEW'),
                    'status'               => 1,
                ]);
                TenantRolePermission::create([
                    'tenant_role_permission_id' => (string) Str::uuid(),
                    'tenant_id'                 => $tenant->tenant_id,
                    'tenant_role_id'            => $role->tenant_role_id,
                    'tenant_permission_id'      => $perm->tenant_permission_id,
                ]);
            }

            TenantUserRole::create([
                'tenant_user_role_id' => (string) Str::uuid(),
                'tenant_id'           => $tenant->tenant_id,
                'user_id'             => $user->user_id,
                'tenant_role_id'      => $role->tenant_role_id,
            ]);
        }

        $this->tokenA = $this->userA->createToken(
            'acc-a',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;
        $this->tokenB = $this->userB->createToken(
            'acc-b',
            ['tenant:' . $this->tenantB->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    protected function authHeaders(string $token, string $tenantId): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID'   => $tenantId,
            'Accept'        => 'application/json',
        ];
    }

    #[Test]
    public function can_create_list_show_update_and_soft_delete_account(): void
    {
        $createResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/accounts', [
                'code'         => '1000',
                'name'         => 'Cash',
                'account_type' => 1,
                'description'  => 'Cash on hand',
                'is_active'    => true,
            ]);

        $createResponse->assertStatus(201);
        $accountId = $createResponse->json('data.account_id');
        $this->assertNotEmpty($accountId);

        $this->assertDatabaseHas('fin_accounts', [
            'account_id'  => $accountId,
            'tenant_id'   => $this->tenantA->tenant_id,
            'code'        => '1000',
            'row_version' => 1,
        ]);

        $listResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/accounting/accounts');
        $listResponse->assertStatus(200);
        $ids = collect($listResponse->json('data'))->pluck('account_id')->all();
        $this->assertContains($accountId, $ids);

        $showResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/accounting/accounts/' . $accountId);
        $showResponse->assertStatus(200)
            ->assertJsonPath('data.code', '1000');

        $updateResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->putJson('/api/accounting/accounts/' . $accountId, [
                'name' => 'Cash (updated)',
            ]);
        $updateResponse->assertStatus(200);

        $this->assertDatabaseHas('fin_accounts', [
            'account_id'  => $accountId,
            'name'        => 'Cash (updated)',
            'row_version' => 2,
        ]);

        $deleteResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->deleteJson('/api/accounting/accounts/' . $accountId);
        $deleteResponse->assertStatus(200);

        $this->assertSoftDeleted('fin_accounts', ['account_id' => $accountId]);
    }

    #[Test]
    public function tenant_isolation_prevents_cross_tenant_access(): void
    {
        $createResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/accounts', [
                'code'         => '1100',
                'name'         => 'Bank A',
                'account_type' => 1,
            ]);
        $createResponse->assertStatus(201);
        $accountIdA = $createResponse->json('data.account_id');

        $showAsB = $this->withHeaders($this->authHeaders($this->tokenB, $this->tenantB->tenant_id))
            ->getJson('/api/accounting/accounts/' . $accountIdA);
        $this->assertTrue(
            in_array($showAsB->status(), [403, 404]),
            'Expected isolation deny (403/404), got ' . $showAsB->status()
        );

        $accountB = Account::withoutGlobalScopes()->create([
            'account_id'   => (string) Str::uuid(),
            'tenant_id'    => $this->tenantB->tenant_id,
            'code'         => '1200',
            'name'         => 'Bank B',
            'account_type' => 1,
            'level'        => 1,
            'is_active'    => true,
            'row_version'  => 1,
        ]);

        $listAsA = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/accounting/accounts');
        $listAsA->assertStatus(200);
        $ids = collect($listAsA->json('data'))->pluck('account_id')->all();
        $this->assertNotContains($accountB->account_id, $ids);
        $this->assertContains($accountIdA, $ids);

        $showAsA = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/accounting/accounts/' . $accountB->account_id);
        $this->assertTrue(
            in_array($showAsA->status(), [403, 404]),
            'Expected isolation deny for A viewing B record, got ' . $showAsA->status()
        );
    }

    #[Test]
    public function unauthorized_user_cannot_create_account(): void
    {
        $unauthUser = User::factory()->create(['status' => 1]);
        TenantUser::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'user_id'   => $unauthUser->user_id,
            'status'    => 1,
        ]);
        $token = $unauthUser->createToken(
            'acc-unauth',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        $response = $this->withHeaders($this->authHeaders($token, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/accounts', [
                'code'         => 'X',
                'name'         => 'X',
                'account_type' => 1,
            ]);

        $this->assertTrue(
            in_array($response->status(), [401, 403]),
            'Expected permission deny, got ' . $response->status()
        );
    }

    #[Test]
    public function cannot_delete_account_with_children(): void
    {
        $parent = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/accounts', [
                'code'         => '2000',
                'name'         => 'Assets',
                'account_type' => 1,
            ]);
        $parent->assertStatus(201);
        $parentId = $parent->json('data.account_id');

        $child = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/accounts', [
                'code'              => '2100',
                'name'              => 'Current Assets',
                'account_type'      => 1,
                'parent_account_id' => $parentId,
            ]);
        $child->assertStatus(201);

        $deleteResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->deleteJson('/api/accounting/accounts/' . $parentId);

        $this->assertTrue(
            in_array($deleteResponse->status(), [409, 422, 400]),
            'Expected conflict when deleting parent with children, got ' . $deleteResponse->status()
        );
    }
}
