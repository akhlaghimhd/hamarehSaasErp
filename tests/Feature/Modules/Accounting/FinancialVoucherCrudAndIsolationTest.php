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
use App\Modules\Accounting\Models\FinancialVoucher;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-ACC-02.3 — FinancialVoucher full CRUD + post (Draft→Posted) + isolation
 */
class FinancialVoucherCrudAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected string $tokenA;
    protected string $tokenB;
    protected string $accountDebitId;
    protected string $accountCreditId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'VOU_A', 'status' => 1]);
        $this->tenantB = Tenant::factory()->create(['tenant_code' => 'VOU_B', 'status' => 1]);

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
            'code'      => 'vou-full',
            'name'      => 'Voucher Full',
            'status'    => 1,
        ]);
        $roleB = TenantRole::factory()->create([
            'tenant_id' => $this->tenantB->tenant_id,
            'code'      => 'vou-full',
            'name'      => 'Voucher Full',
            'status'    => 1,
        ]);

        $permissionCodes = [
            'accounting.voucher.view',
            'accounting.voucher.create',
            'accounting.voucher.update',
            'accounting.voucher.post',
            'accounting.voucher.delete',
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

        $this->tokenA = $this->userA->createToken('vou-a', ['tenant:' . $this->tenantA->tenant_id])->plainTextToken;
        $this->tokenB = $this->userB->createToken('vou-b', ['tenant:' . $this->tenantB->tenant_id])->plainTextToken;

        // Seed COA via DB::table so primary keys are not stripped by $fillable
        $this->accountDebitId  = (string) Str::uuid();
        $this->accountCreditId = (string) Str::uuid();

        DB::table('fin_accounts')->insert([
            [
                'account_id'   => $this->accountDebitId,
                'tenant_id'    => $this->tenantA->tenant_id,
                'code'         => '1100',
                'name'         => 'Cash',
                'account_type' => 1,
                'level'        => 1,
                'is_active'    => true,
                'created_at'   => now(),
                'row_version'  => 1,
            ],
            [
                'account_id'   => $this->accountCreditId,
                'tenant_id'    => $this->tenantA->tenant_id,
                'code'         => '4100',
                'name'         => 'Revenue',
                'account_type' => 4,
                'level'        => 1,
                'is_active'    => true,
                'created_at'   => now(),
                'row_version'  => 1,
            ],
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

    protected function createDraftViaApi(string $ref = 'V-001'): string
    {
        $res = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/vouchers', [
                'voucher_date'     => '2026-03-15',
                'description'      => 'Test voucher',
                'total_amount'     => 1000,
                'reference_number' => $ref,
            ]);
        $res->assertStatus(201);

        return $res->json('data.voucher_id');
    }

    protected function addBalancedLines(string $voucherId): void
    {
        DB::table('fin_voucher_items')->insert([
            [
                'item_id'     => (string) Str::uuid(),
                'tenant_id'   => $this->tenantA->tenant_id,
                'voucher_id'  => $voucherId,
                'account_id'  => $this->accountDebitId,
                'description' => 'Debit line',
                'debit'       => 1000.0000,
                'credit'      => 0,
                'created_at'  => now(),
                'row_version' => 1,
            ],
            [
                'item_id'     => (string) Str::uuid(),
                'tenant_id'   => $this->tenantA->tenant_id,
                'voucher_id'  => $voucherId,
                'account_id'  => $this->accountCreditId,
                'description' => 'Credit line',
                'debit'       => 0,
                'credit'      => 1000.0000,
                'created_at'  => now(),
                'row_version' => 1,
            ],
        ]);
    }

    #[Test]
    public function can_create_list_show_update_and_soft_delete_draft_voucher(): void
    {
        $voucherId = $this->createDraftViaApi('V-CRUD-01');

        $this->assertDatabaseHas('fin_vouchers', [
            'voucher_id'       => $voucherId,
            'tenant_id'        => $this->tenantA->tenant_id,
            'reference_number' => 'V-CRUD-01',
            'status'           => 1,
            'row_version'      => 1,
        ]);

        $list = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/accounting/vouchers');
        $list->assertStatus(200);
        $ids = collect($list->json('data'))->pluck('voucher_id')->all();
        $this->assertContains($voucherId, $ids);

        $show = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/accounting/vouchers/' . $voucherId);
        $show->assertStatus(200)->assertJsonPath('data.reference_number', 'V-CRUD-01');

        $update = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->putJson('/api/accounting/vouchers/' . $voucherId, [
                'description' => 'Updated description',
            ]);
        $update->assertStatus(200);

        $this->assertDatabaseHas('fin_vouchers', [
            'voucher_id'  => $voucherId,
            'description' => 'Updated description',
            'row_version' => 2,
        ]);

        $delete = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->deleteJson('/api/accounting/vouchers/' . $voucherId);
        $delete->assertStatus(200);
        $this->assertSoftDeleted('fin_vouchers', ['voucher_id' => $voucherId]);
    }

    #[Test]
    public function can_post_balanced_draft_voucher(): void
    {
        $voucherId = $this->createDraftViaApi('V-POST-01');
        $this->addBalancedLines($voucherId);

        $post = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/vouchers/' . $voucherId . '/post');
        $post->assertStatus(200);

        $this->assertDatabaseHas('fin_vouchers', [
            'voucher_id'   => $voucherId,
            'status'       => 2, // Posted
            'total_amount' => 1000.0000,
        ]);

        // Posted cannot be updated or deleted
        $updatePosted = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->putJson('/api/accounting/vouchers/' . $voucherId, ['description' => 'Nope']);
        $this->assertTrue(in_array($updatePosted->status(), [409, 422, 400]));

        $deletePosted = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->deleteJson('/api/accounting/vouchers/' . $voucherId);
        $this->assertTrue(in_array($deletePosted->status(), [409, 422, 400]));
    }

    #[Test]
    public function cannot_post_unbalanced_or_empty_voucher(): void
    {
        $voucherId = $this->createDraftViaApi('V-UNBAL');

        // Empty lines
        $postEmpty = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/vouchers/' . $voucherId . '/post');
        $this->assertTrue(in_array($postEmpty->status(), [409, 422, 400]));

        // Unbalanced lines
        DB::table('fin_voucher_items')->insert([
            'item_id'     => (string) Str::uuid(),
            'tenant_id'   => $this->tenantA->tenant_id,
            'voucher_id'  => $voucherId,
            'account_id'  => $this->accountDebitId,
            'description' => 'Only debit',
            'debit'       => 500.0000,
            'credit'      => 0,
            'created_at'  => now(),
            'row_version' => 1,
        ]);

        $postUnbal = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/vouchers/' . $voucherId . '/post');
        $this->assertTrue(in_array($postUnbal->status(), [409, 422, 400]));
    }

    #[Test]
    public function tenant_isolation_prevents_cross_tenant_access(): void
    {
        $voucherIdA = $this->createDraftViaApi('V-ISO-A');

        $showAsB = $this->withHeaders($this->authHeaders($this->tokenB, $this->tenantB->tenant_id))
            ->getJson('/api/accounting/vouchers/' . $voucherIdA);
        $this->assertTrue(in_array($showAsB->status(), [403, 404]));

        $voucherBId = (string) Str::uuid();
        DB::table('fin_vouchers')->insert([
            'voucher_id'       => $voucherBId,
            'tenant_id'        => $this->tenantB->tenant_id,
            'voucher_date'     => '2026-03-15',
            'description'      => 'B voucher',
            'total_amount'     => 100,
            'reference_number' => 'V-ISO-B',
            'status'           => 1,
            'created_at'       => now(),
            'row_version'      => 1,
        ]);

        $listAsA = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/accounting/vouchers');
        $listAsA->assertStatus(200);
        $ids = collect($listAsA->json('data'))->pluck('voucher_id')->all();
        $this->assertNotContains($voucherBId, $ids);
        $this->assertContains($voucherIdA, $ids);
    }

    #[Test]
    public function unauthorized_user_cannot_create_voucher(): void
    {
        $unauthUser = User::factory()->create(['status' => 1]);
        TenantUser::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'user_id'   => $unauthUser->user_id,
            'status'    => 1,
        ]);
        $token = $unauthUser->createToken('vou-unauth', ['tenant:' . $this->tenantA->tenant_id])->plainTextToken;

        $response = $this->withHeaders($this->authHeaders($token, $this->tenantA->tenant_id))
            ->postJson('/api/accounting/vouchers', [
                'voucher_date'     => '2026-01-01',
                'description'      => 'X',
                'total_amount'     => 1,
                'reference_number' => 'X-1',
            ]);

        $this->assertTrue(in_array($response->status(), [401, 403]));
    }
}
