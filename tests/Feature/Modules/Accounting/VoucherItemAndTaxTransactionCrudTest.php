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
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-ACC-02.4 — FinancialVoucherItem + TaxTransaction CRUD + draft-only item mutation + isolation
 */
class VoucherItemAndTaxTransactionCrudTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected string $tokenA;
    protected string $tokenB;
    protected string $accountId;
    protected string $voucherId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'IT_A', 'status' => 1]);
        $this->tenantB = Tenant::factory()->create(['tenant_code' => 'IT_B', 'status' => 1]);

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
            'code'      => 'it-full',
            'name'      => 'Item Tax Full',
            'status'    => 1,
        ]);
        $roleB = TenantRole::factory()->create([
            'tenant_id' => $this->tenantB->tenant_id,
            'code'      => 'it-full',
            'name'      => 'Item Tax Full',
            'status'    => 1,
        ]);

        $permissionCodes = [
            'accounting.voucher.view',
            'accounting.voucher.create',
            'accounting.voucher.post',
            'accounting.voucher-item.view',
            'accounting.voucher-item.create',
            'accounting.voucher-item.update',
            'accounting.voucher-item.delete',
            'accounting.tax-transaction.view',
            'accounting.tax-transaction.create',
            'accounting.tax-transaction.update',
            'accounting.tax-transaction.delete',
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
                    'action_type'          => 'EXECUTE',
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

        $this->tokenA = $this->userA->createToken('it-a', ['tenant:' . $this->tenantA->tenant_id])->plainTextToken;
        $this->tokenB = $this->userB->createToken('it-b', ['tenant:' . $this->tenantB->tenant_id])->plainTextToken;

        $this->accountId = (string) Str::uuid();
        DB::table('fin_accounts')->insert([
            'account_id'   => $this->accountId,
            'tenant_id'    => $this->tenantA->tenant_id,
            'code'         => '1100',
            'name'         => 'Cash',
            'account_type' => 1,
            'level'        => 1,
            'is_active'    => true,
            'created_at'   => now(),
            'row_version'  => 1,
        ]);

        $this->voucherId = (string) Str::uuid();
        DB::table('fin_vouchers')->insert([
            'voucher_id'       => $this->voucherId,
            'tenant_id'        => $this->tenantA->tenant_id,
            'voucher_date'     => '2026-03-15',
            'description'      => 'Draft voucher',
            'total_amount'     => 0,
            'reference_number' => 'IT-V-001',
            'status'           => 1, // Draft
            'created_at'       => now(),
            'row_version'      => 1,
        ]);

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
    public function can_crud_voucher_item_on_draft_voucher(): void
    {
        $create = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/voucher-items', [
                'voucher_id'  => $this->voucherId,
                'account_id'  => $this->accountId,
                'description' => 'Debit line',
                'debit'       => 500,
                'credit'      => 0,
            ]);
        $create->assertStatus(201);
        $itemId = $create->json('data.item_id');
        $this->assertNotEmpty($itemId);

        $list = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/accounting/voucher-items?voucher_id=' . $this->voucherId);
        $list->assertStatus(200);
        $ids = collect($list->json('data'))->pluck('item_id')->all();
        $this->assertContains($itemId, $ids);

        $show = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/accounting/voucher-items/' . $itemId);
        $show->assertStatus(200)->assertJsonPath('data.item_id', $itemId);

        $update = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->putJson('/api/accounting/voucher-items/' . $itemId, [
                'description' => 'Updated line',
                'debit'       => 750,
                'credit'      => 0,
            ]);
        $update->assertStatus(200);

        $this->assertDatabaseHas('fin_voucher_items', [
            'item_id'     => $itemId,
            'description' => 'Updated line',
            'row_version' => 2,
        ]);

        $delete = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->deleteJson('/api/accounting/voucher-items/' . $itemId);
        $delete->assertStatus(200);
        $this->assertSoftDeleted('fin_voucher_items', ['item_id' => $itemId]);
    }

    #[Test]
    public function cannot_mutate_items_on_posted_voucher(): void
    {
        // Mark voucher as Posted
        DB::table('fin_vouchers')->where('voucher_id', $this->voucherId)->update(['status' => 2]);

        $create = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/voucher-items', [
                'voucher_id'  => $this->voucherId,
                'account_id'  => $this->accountId,
                'description' => 'Should fail',
                'debit'       => 100,
                'credit'      => 0,
            ]);
        $this->assertTrue(in_array($create->status(), [409, 422, 400]));
    }

    #[Test]
    public function can_crud_tax_transaction(): void
    {
        $create = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/tax-transactions', [
                'transaction_date' => '2026-03-15',
                'tax_type'         => 1,
                'base_amount'      => 1000,
                'tax_amount'       => 90,
                'tax_rate'         => 9,
            ]);
        $create->assertStatus(201);
        $txId = $create->json('data.transaction_id');
        $this->assertNotEmpty($txId);

        $list = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/accounting/tax-transactions');
        $list->assertStatus(200);
        $ids = collect($list->json('data'))->pluck('transaction_id')->all();
        $this->assertContains($txId, $ids);

        $show = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/accounting/tax-transactions/' . $txId);
        $show->assertStatus(200);

        $update = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->putJson('/api/accounting/tax-transactions/' . $txId, [
                'tax_amount' => 95,
            ]);
        $update->assertStatus(200);

        $this->assertDatabaseHas('fin_acc_tax_transactions', [
            'transaction_id' => $txId,
            'tax_amount'     => 95.0000,
            'row_version'    => 2,
        ]);

        $delete = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->deleteJson('/api/accounting/tax-transactions/' . $txId);
        $delete->assertStatus(200);
        $this->assertSoftDeleted('fin_acc_tax_transactions', ['transaction_id' => $txId]);
    }

    #[Test]
    public function tenant_isolation_on_tax_transactions(): void
    {
        $create = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/tax-transactions', [
                'transaction_date' => '2026-01-01',
                'tax_type'         => 1,
                'base_amount'      => 100,
                'tax_amount'       => 9,
                'tax_rate'         => 9,
            ]);
        $create->assertStatus(201);
        $txIdA = $create->json('data.transaction_id');

        $showAsB = $this->withHeaders($this->authHeaders($this->tokenB, $this->tenantB->tenant_id))
            ->getJson('/api/accounting/tax-transactions/' . $txIdA);
        $this->assertTrue(in_array($showAsB->status(), [403, 404]));
    }
}
