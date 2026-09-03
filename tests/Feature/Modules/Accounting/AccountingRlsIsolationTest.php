<?php

namespace Tests\Feature\Modules\Accounting;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\FiscalPeriod;
use App\Modules\Accounting\Models\FinancialVoucher;
use App\Modules\Accounting\Models\FinancialVoucherItem;
use App\Modules\Accounting\Models\TaxTransaction;
use App\Base\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-ACC-01 — PostgreSQL RLS + TenantScoped isolation for Accounting operational tables.
 *
 * Tables under test:
 *   fin_accounts, fin_fiscal_periods, fin_vouchers, fin_voucher_items, fin_acc_tax_transactions
 *
 * Pattern follows RlsMultiTableIsolationTest (IdentityCore) and Tenant Isolation Architecture Standard §4 / §10.
 */
class AccountingRlsIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;

    protected string $accountAId;
    protected string $accountBId;
    protected string $periodAId;
    protected string $periodBId;
    protected string $voucherAId;
    protected string $voucherBId;
    protected string $itemAId;
    protected string $itemBId;
    protected string $taxAId;
    protected string $taxBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureRlsInfrastructure([
            'fin_accounts',
            'fin_fiscal_periods',
            'fin_vouchers',
            'fin_voucher_items',
            'fin_acc_tax_transactions',
        ]);

        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'ACC_RLS_A', 'status' => 1]);
        $this->tenantB = Tenant::factory()->create(['tenant_code' => 'ACC_RLS_B', 'status' => 1]);

        $this->accountAId = (string) Str::uuid();
        $this->accountBId = (string) Str::uuid();
        $this->periodAId  = (string) Str::uuid();
        $this->periodBId  = (string) Str::uuid();
        $this->voucherAId = (string) Str::uuid();
        $this->voucherBId = (string) Str::uuid();
        $this->itemAId    = (string) Str::uuid();
        $this->itemBId    = (string) Str::uuid();
        $this->taxAId     = (string) Str::uuid();
        $this->taxBId     = (string) Str::uuid();

        // Seed accounts (required by voucher_items FK)
        DB::table('fin_accounts')->insert([
            [
                'account_id'   => $this->accountAId,
                'tenant_id'    => $this->tenantA->tenant_id,
                'code'         => '1000',
                'name'         => 'Cash A',
                'account_type' => 1,
                'level'        => 1,
                'is_active'    => true,
                'created_at'   => now(),
                'row_version'  => 1,
            ],
            [
                'account_id'   => $this->accountBId,
                'tenant_id'    => $this->tenantB->tenant_id,
                'code'         => '1000',
                'name'         => 'Cash B',
                'account_type' => 1,
                'level'        => 1,
                'is_active'    => true,
                'created_at'   => now(),
                'row_version'  => 1,
            ],
        ]);

        DB::table('fin_fiscal_periods')->insert([
            [
                'period_id'   => $this->periodAId,
                'tenant_id'   => $this->tenantA->tenant_id,
                'name'        => 'FY2026-A',
                'start_date'  => '2026-01-01',
                'end_date'    => '2026-12-31',
                'is_closed'   => false,
                'created_at'  => now(),
                'row_version' => 1,
            ],
            [
                'period_id'   => $this->periodBId,
                'tenant_id'   => $this->tenantB->tenant_id,
                'name'        => 'FY2026-B',
                'start_date'  => '2026-01-01',
                'end_date'    => '2026-12-31',
                'is_closed'   => false,
                'created_at'  => now(),
                'row_version' => 1,
            ],
        ]);

        DB::table('fin_vouchers')->insert([
            [
                'voucher_id'       => $this->voucherAId,
                'tenant_id'        => $this->tenantA->tenant_id,
                'voucher_date'     => '2026-03-01',
                'description'      => 'Voucher A',
                'total_amount'     => 1000.0000,
                'reference_number' => 'V-A-001',
                'status'           => 1,
                'created_at'       => now(),
                'row_version'      => 1,
            ],
            [
                'voucher_id'       => $this->voucherBId,
                'tenant_id'        => $this->tenantB->tenant_id,
                'voucher_date'     => '2026-03-01',
                'description'      => 'Voucher B',
                'total_amount'     => 2000.0000,
                'reference_number' => 'V-B-001',
                'status'           => 1,
                'created_at'       => now(),
                'row_version'      => 1,
            ],
        ]);

        DB::table('fin_voucher_items')->insert([
            [
                'item_id'     => $this->itemAId,
                'tenant_id'   => $this->tenantA->tenant_id,
                'voucher_id'  => $this->voucherAId,
                'account_id'  => $this->accountAId,
                'description' => 'Item A',
                'debit'       => 1000.0000,
                'credit'      => 0,
                'created_at'  => now(),
                'row_version' => 1,
            ],
            [
                'item_id'     => $this->itemBId,
                'tenant_id'   => $this->tenantB->tenant_id,
                'voucher_id'  => $this->voucherBId,
                'account_id'  => $this->accountBId,
                'description' => 'Item B',
                'debit'       => 2000.0000,
                'credit'      => 0,
                'created_at'  => now(),
                'row_version' => 1,
            ],
        ]);

        DB::table('fin_acc_tax_transactions')->insert([
            [
                'transaction_id'   => $this->taxAId,
                'tenant_id'        => $this->tenantA->tenant_id,
                'transaction_date' => '2026-03-01',
                'tax_type'         => 1,
                'base_amount'      => 1000.0000,
                'tax_amount'       => 90.0000,
                'tax_rate'         => 9.00,
                'created_at'       => now(),
                'row_version'      => 1,
            ],
            [
                'transaction_id'   => $this->taxBId,
                'tenant_id'        => $this->tenantB->tenant_id,
                'transaction_date' => '2026-03-01',
                'tax_type'         => 1,
                'base_amount'      => 2000.0000,
                'tax_amount'       => 180.0000,
                'tax_rate'         => 9.00,
                'created_at'       => now(),
                'row_version'      => 1,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        try {
            DB::statement('RESET ROLE');
            DB::statement("SELECT set_config('app.current_tenant_id', '', true)");
        } catch (\Throwable $e) {
            // ignore cleanup errors
        }

        TenantContext::resetInstance();
        parent::tearDown();
    }

    protected function ensureRlsInfrastructure(array $tables): void
    {
        DB::statement('GRANT USAGE ON SCHEMA public TO app_user');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO app_user');
        DB::statement('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO app_user');

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON {$table}
                FOR ALL
                USING (
                    tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
                )
                WITH CHECK (
                    tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
                )
            ");
        }
    }

    protected function actAsAppUser(): void
    {
        DB::statement('SET ROLE app_user');
    }

    // ------------------------------------------------------------------
    // RLS isolation (even when Global Scopes are bypassed)
    // ------------------------------------------------------------------

    #[Test]
    public function rls_isolates_fin_accounts_even_without_global_scopes(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantA->tenant_id]);

        $rows = Account::withoutGlobalScopes()->get();
        $this->assertCount(1, $rows);
        $this->assertEquals($this->accountAId, $rows->first()->account_id);
    }

    #[Test]
    public function rls_isolates_fin_fiscal_periods_even_without_global_scopes(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantA->tenant_id]);

        $rows = FiscalPeriod::withoutGlobalScopes()->get();
        $this->assertCount(1, $rows);
        $this->assertEquals($this->periodAId, $rows->first()->period_id);
    }

    #[Test]
    public function rls_isolates_fin_vouchers_even_without_global_scopes(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantA->tenant_id]);

        $rows = FinancialVoucher::withoutGlobalScopes()->get();
        $this->assertCount(1, $rows);
        $this->assertEquals($this->voucherAId, $rows->first()->voucher_id);
    }

    #[Test]
    public function rls_isolates_fin_voucher_items_even_without_global_scopes(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantA->tenant_id]);

        $rows = FinancialVoucherItem::withoutGlobalScopes()->get();
        $this->assertCount(1, $rows);
        $this->assertEquals($this->itemAId, $rows->first()->item_id);
    }

    #[Test]
    public function rls_isolates_fin_acc_tax_transactions_even_without_global_scopes(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantA->tenant_id]);

        $rows = TaxTransaction::withoutGlobalScopes()->get();
        $this->assertCount(1, $rows);
        $this->assertEquals($this->taxAId, $rows->first()->transaction_id);
    }

    #[Test]
    public function rls_returns_empty_when_tenant_context_missing(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', '', false)");

        $this->assertCount(0, Account::withoutGlobalScopes()->get());
        $this->assertCount(0, FiscalPeriod::withoutGlobalScopes()->get());
        $this->assertCount(0, FinancialVoucher::withoutGlobalScopes()->get());
        $this->assertCount(0, FinancialVoucherItem::withoutGlobalScopes()->get());
        $this->assertCount(0, TaxTransaction::withoutGlobalScopes()->get());
    }

    #[Test]
    public function rls_blocks_cross_tenant_insert_on_fin_accounts(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantA->tenant_id]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('fin_accounts')->insert([
            'account_id'   => (string) Str::uuid(),
            'tenant_id'    => $this->tenantB->tenant_id,
            'code'         => 'HACK',
            'name'         => 'Hack Account',
            'account_type' => 1,
            'level'        => 1,
            'is_active'    => true,
            'created_at'   => now(),
            'row_version'  => 1,
        ]);
    }

    // ------------------------------------------------------------------
    // Application-layer TenantScoped (Global Scope)
    // ------------------------------------------------------------------

    #[Test]
    public function tenant_scoped_global_scope_isolates_accounts(): void
    {
        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);

        $rows = Account::all();
        $this->assertCount(1, $rows);
        $this->assertEquals($this->accountAId, $rows->first()->account_id);

        // Switch to tenant B
        TenantContext::getInstance()->setTenantId($this->tenantB->tenant_id);
        app()->instance('current_tenant_id', $this->tenantB->tenant_id);

        $rowsB = Account::all();
        $this->assertCount(1, $rowsB);
        $this->assertEquals($this->accountBId, $rowsB->first()->account_id);
    }

    #[Test]
    public function tenant_scoped_global_scope_isolates_all_accounting_tables(): void
    {
        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);

        $this->assertCount(1, FiscalPeriod::all());
        $this->assertCount(1, FinancialVoucher::all());
        $this->assertCount(1, FinancialVoucherItem::all());
        $this->assertCount(1, TaxTransaction::all());

        $this->assertEquals($this->periodAId, FiscalPeriod::first()->period_id);
        $this->assertEquals($this->voucherAId, FinancialVoucher::first()->voucher_id);
        $this->assertEquals($this->itemAId, FinancialVoucherItem::first()->item_id);
        $this->assertEquals($this->taxAId, TaxTransaction::first()->transaction_id);
    }
}
