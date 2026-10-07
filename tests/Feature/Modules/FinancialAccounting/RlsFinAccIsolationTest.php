<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FinancialAccounting;

use App\Base\Context\TenantContext;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\Ledger;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FIN-P0-08 — RLS isolation for FinancialAccounting GL tables.
 * Pattern mirrors RlsTenantRolesIsolationTest (app_user + FORCE RLS).
 */
class RlsFinAccIsolationTest extends TestCase
{
    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected string $ledgerAId;

    protected string $ledgerBId;

    protected string $accountAId;

    protected string $accountBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureRlsInfrastructure();

        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'FIN_RLS_A']);
        $this->tenantB = Tenant::factory()->create(['tenant_code' => 'FIN_RLS_B']);

        $this->ledgerAId = (string) Str::uuid();
        $this->ledgerBId = (string) Str::uuid();
        $this->accountAId = (string) Str::uuid();
        $this->accountBId = (string) Str::uuid();

        $companyA = (string) Str::uuid();
        $companyB = (string) Str::uuid();

        DB::table('fin_acc_ledgers')->insert([
            [
                'ledger_id'   => $this->ledgerAId,
                'tenant_id'   => $this->tenantA->tenant_id,
                'company_id'  => $companyA,
                'code'        => 'LG',
                'name'        => 'Leading A',
                'is_leading'  => true,
                'status'      => 1,
                'created_at'  => now(),
                'row_version' => 1,
            ],
            [
                'ledger_id'   => $this->ledgerBId,
                'tenant_id'   => $this->tenantB->tenant_id,
                'company_id'  => $companyB,
                'code'        => 'LG',
                'name'        => 'Leading B',
                'is_leading'  => true,
                'status'      => 1,
                'created_at'  => now(),
                'row_version' => 1,
            ],
        ]);

        DB::table('fin_acc_accounts')->insert([
            [
                'account_id'         => $this->accountAId,
                'tenant_id'          => $this->tenantA->tenant_id,
                'account_code'       => '1101',
                'name'               => 'Cash A',
                'account_type'       => 1,
                'account_level'      => 2,
                'normal_balance'     => 1,
                'is_control_account' => false,
                'is_postable'        => true,
                'status'             => 1,
                'created_at'         => now(),
                'row_version'        => 1,
            ],
            [
                'account_id'         => $this->accountBId,
                'tenant_id'          => $this->tenantB->tenant_id,
                'account_code'       => '1101',
                'name'               => 'Cash B',
                'account_type'       => 1,
                'account_level'      => 2,
                'normal_balance'     => 1,
                'is_control_account' => false,
                'is_postable'        => true,
                'status'             => 1,
                'created_at'         => now(),
                'row_version'        => 1,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        try {
            DB::statement('RESET ROLE');
            DB::statement("SELECT set_config('app.current_tenant_id', '', true)");
        } catch (\Throwable $e) {
            // ignore
        }

        TenantContext::resetInstance();
        parent::tearDown();
    }

    protected function ensureRlsInfrastructure(): void
    {
        DB::statement('GRANT USAGE ON SCHEMA public TO app_user');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO app_user');
        DB::statement('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO app_user');

        foreach (['fin_acc_ledgers', 'fin_acc_accounts'] as $table) {
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

    #[Test]
    public function rls_blocks_cross_tenant_ledgers_even_without_global_scopes(): void
    {
        $this->actAsAppUser();

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [
            $this->tenantA->tenant_id,
        ]);

        $rows = Ledger::withoutGlobalScopes()->get();

        $this->assertCount(1, $rows);
        $this->assertEquals($this->ledgerAId, $rows->first()->ledger_id);
    }

    #[Test]
    public function rls_blocks_cross_tenant_accounts(): void
    {
        $this->actAsAppUser();

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [
            $this->tenantA->tenant_id,
        ]);

        $rows = Account::withoutGlobalScopes()->get();

        $this->assertCount(1, $rows);
        $this->assertEquals('Cash A', $rows->first()->name);
    }

    #[Test]
    public function rls_returns_empty_when_tenant_context_missing(): void
    {
        $this->actAsAppUser();

        DB::statement("SELECT set_config('app.current_tenant_id', '', false)");

        $this->assertCount(0, Ledger::withoutGlobalScopes()->get());
        $this->assertCount(0, Account::withoutGlobalScopes()->get());
    }

    #[Test]
    public function rls_blocks_insert_of_wrong_tenant_account(): void
    {
        $this->actAsAppUser();

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [
            $this->tenantA->tenant_id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('fin_acc_accounts')->insert([
            'account_id'         => (string) Str::uuid(),
            'tenant_id'          => $this->tenantB->tenant_id,
            'account_code'       => '9999',
            'name'               => 'Hack',
            'account_type'       => 1,
            'account_level'      => 1,
            'normal_balance'     => 1,
            'is_control_account' => false,
            'is_postable'        => true,
            'status'             => 1,
            'created_at'         => now(),
            'row_version'        => 1,
        ]);
    }
}
