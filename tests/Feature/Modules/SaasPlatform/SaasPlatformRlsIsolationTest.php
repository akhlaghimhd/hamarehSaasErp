<?php

namespace Tests\Feature\Modules\SaasPlatform;

use Tests\TestCase;
use App\Base\Context\TenantContext;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\SaasPlatform\Models\TenantWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L1-10 – PostgreSQL RLS isolation for Layer 1 tables with tenant_id.
 *
 * Pattern follows AccountingRlsIsolationTest / IdentityCore RlsMultiTableIsolationTest:
 * seed as migration role, then SET ROLE app_user so FORCE RLS is actually enforced
 * (superuser / table owner bypasses RLS even with FORCE).
 */
class SaasPlatformRlsIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected string $walletAId;
    protected string $walletBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureRlsInfrastructure(['tenant_wallets']);

        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'SAAS_RLS_A', 'status' => 1]);
        $this->tenantB = Tenant::factory()->create(['tenant_code' => 'SAAS_RLS_B', 'status' => 1]);

        $this->walletAId = (string) Str::uuid();
        $this->walletBId = (string) Str::uuid();

        // Seed as current (owner) role so inserts are not blocked by RLS
        DB::table('tenant_wallets')->insert([
            [
                'wallet_id'   => $this->walletAId,
                'tenant_id'   => $this->tenantA->tenant_id,
                'balance'     => 100,
                'status'      => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
                'row_version' => 1,
            ],
            [
                'wallet_id'   => $this->walletBId,
                'tenant_id'   => $this->tenantB->tenant_id,
                'balance'     => 200,
                'status'      => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
                'row_version' => 1,
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

    #[Test]
    public function soft_deleted_tenant_code_can_be_reused_after_partial_unique_fix(): void
    {
        $tenant = Tenant::factory()->create([
            'tenant_code' => 'REUSE_CODE',
            'slug'        => 'reuse-slug',
        ]);

        $tenant->delete();

        $recreated = Tenant::factory()->create([
            'tenant_code' => 'REUSE_CODE',
            'slug'        => 'reuse-slug',
        ]);

        $this->assertNotNull($recreated->tenant_id);
        $this->assertSame('REUSE_CODE', $recreated->tenant_code);
    }

    #[Test]
    public function rls_isolates_tenant_wallets_for_app_user(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantA->tenant_id]);

        $rows = TenantWallet::query()->get();
        $this->assertCount(1, $rows);
        $this->assertSame($this->walletAId, $rows->first()->wallet_id);
        $this->assertSame($this->tenantA->tenant_id, $rows->first()->tenant_id);
    }

    #[Test]
    public function rls_returns_empty_when_tenant_context_cleared(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', '', false)");

        $this->assertCount(0, TenantWallet::query()->get());
    }

    #[Test]
    public function rls_blocks_cross_tenant_insert_on_tenant_wallets(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantA->tenant_id]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('tenant_wallets')->insert([
            'wallet_id'   => (string) Str::uuid(),
            'tenant_id'   => $this->tenantB->tenant_id,
            'balance'     => 1,
            'status'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
            'row_version' => 1,
        ]);
    }
}
