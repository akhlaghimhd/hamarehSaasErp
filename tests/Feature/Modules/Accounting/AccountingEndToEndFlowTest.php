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
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-ACC-03 — End-to-end Accounting happy path via public API:
 * Account → FiscalPeriod → Draft Voucher → Items (balanced) → Post → TaxTransaction
 */
class AccountingEndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'E2E_ACC', 'status' => 1]);
        $this->user = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->user->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'acc-e2e',
            'name'      => 'Accounting E2E',
            'status'    => 1,
        ]);

        $permissionCodes = [
            'accounting.account.view',
            'accounting.account.create',
            'accounting.fiscal-period.view',
            'accounting.fiscal-period.create',
            'accounting.voucher.view',
            'accounting.voucher.create',
            'accounting.voucher.post',
            'accounting.voucher-item.view',
            'accounting.voucher-item.create',
            'accounting.tax-transaction.view',
            'accounting.tax-transaction.create',
        ];

        foreach ($permissionCodes as $code) {
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenant->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'Accounting',
                'action_type'          => 'EXECUTE',
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

        $this->token = $this->user->createToken('e2e', ['tenant:' . $this->tenant->tenant_id])->plainTextToken;

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

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ];
    }

    #[Test]
    public function end_to_end_accounting_happy_path(): void
    {
        // 1) Chart of Accounts — two accounts (debit + credit)
        $cash = $this->withHeaders($this->headers())
            ->postJson('/api/accounting/accounts', [
                'code'         => '1100',
                'name'         => 'Cash',
                'account_type' => 1,
                'is_active'    => true,
            ]);
        $cash->assertStatus(201);
        $cashId = $cash->json('data.account_id');

        $revenue = $this->withHeaders($this->headers())
            ->postJson('/api/accounting/accounts', [
                'code'         => '4100',
                'name'         => 'Revenue',
                'account_type' => 4,
                'is_active'    => true,
            ]);
        $revenue->assertStatus(201);
        $revenueId = $revenue->json('data.account_id');

        // 2) Fiscal period
        $period = $this->withHeaders($this->headers())
            ->postJson('/api/accounting/fiscal-periods', [
                'name'       => 'FY2026',
                'start_date' => '2026-01-01',
                'end_date'   => '2026-12-31',
            ]);
        $period->assertStatus(201);
        $this->assertNotEmpty($period->json('data.period_id'));

        // 3) Draft voucher header
        $voucher = $this->withHeaders($this->headers())
            ->postJson('/api/accounting/vouchers', [
                'voucher_date'     => '2026-03-15',
                'description'      => 'E2E sales receipt',
                'total_amount'     => 1000,
                'reference_number' => 'E2E-V-001',
            ]);
        $voucher->assertStatus(201);
        $voucherId = $voucher->json('data.voucher_id');
        $this->assertSame(1, (int) $voucher->json('data.status'));

        // 4) Balanced lines
        $line1 = $this->withHeaders($this->headers())
            ->postJson('/api/accounting/voucher-items', [
                'voucher_id'  => $voucherId,
                'account_id'  => $cashId,
                'description' => 'Cash received',
                'debit'       => 1000,
                'credit'      => 0,
            ]);
        $line1->assertStatus(201);

        $line2 = $this->withHeaders($this->headers())
            ->postJson('/api/accounting/voucher-items', [
                'voucher_id'  => $voucherId,
                'account_id'  => $revenueId,
                'description' => 'Revenue recognized',
                'debit'       => 0,
                'credit'      => 1000,
            ]);
        $line2->assertStatus(201);

        // 5) Post draft → Posted
        $post = $this->withHeaders($this->headers())
            ->postJson('/api/accounting/vouchers/' . $voucherId . '/post');
        $post->assertStatus(200);

        $this->assertDatabaseHas('fin_vouchers', [
            'voucher_id'   => $voucherId,
            'tenant_id'    => $this->tenant->tenant_id,
            'status'       => 2,
            'total_amount' => 1000.0000,
        ]);

        // 6) Tax transaction (logical reference to voucher)
        $tax = $this->withHeaders($this->headers())
            ->postJson('/api/accounting/tax-transactions', [
                'transaction_date'        => '2026-03-15',
                'tax_type'                => 1,
                'base_amount'             => 1000,
                'tax_amount'              => 90,
                'tax_rate'                => 9,
                'reference_document_type' => 'FINANCIAL_VOUCHER',
                'reference_document_id'   => $voucherId,
            ]);
        $tax->assertStatus(201);
        $this->assertNotEmpty($tax->json('data.transaction_id'));

        // 7) Outbox events present for key aggregates
        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'      => $this->tenant->tenant_id,
            'aggregate_type' => 'fin_vouchers',
            'aggregate_id'   => $voucherId,
            'event_type'     => 'accounting.voucher.posted.v1',
        ]);
    }
}
