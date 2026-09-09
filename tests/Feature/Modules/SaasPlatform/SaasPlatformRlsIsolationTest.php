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
 * L1-10 – Smoke isolation checks for Layer 1 tables that carry tenant_id.
 * Requires RLS policies from 2026_09_09_080001_enable_rls_on_saas_platform_tables
 * and app.current_tenant_id set via TenantContext (FORCE RLS).
 */
class SaasPlatformRlsIsolationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tenant_wallets_belong_to_correct_tenant(): void
    {
        $tenantA = Tenant::factory()->create(['tenant_code' => 'RLS_A']);
        $tenantB = Tenant::factory()->create(['tenant_code' => 'RLS_B']);

        TenantContext::getInstance()->setTenantId($tenantA->tenant_id);
        $walletA = TenantWallet::create([
            'wallet_id'  => (string) Str::uuid(),
            'tenant_id'  => $tenantA->tenant_id,
            'balance'    => 100,
            'status'     => 1,
        ]);

        TenantContext::getInstance()->setTenantId($tenantB->tenant_id);
        $walletB = TenantWallet::create([
            'wallet_id'  => (string) Str::uuid(),
            'tenant_id'  => $tenantB->tenant_id,
            'balance'    => 200,
            'status'     => 1,
        ]);

        TenantContext::getInstance()->setTenantId($tenantA->tenant_id);
        $this->assertSame($tenantA->tenant_id, $walletA->fresh()->tenant_id);

        TenantContext::getInstance()->setTenantId($tenantB->tenant_id);
        $this->assertSame($tenantB->tenant_id, $walletB->fresh()->tenant_id);

        $this->assertNotEquals($walletA->tenant_id, $walletB->tenant_id);
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
    public function rls_hides_other_tenant_wallet_rows(): void
    {
        $tenantA = Tenant::factory()->create(['tenant_code' => 'RLS_HIDE_A']);
        $tenantB = Tenant::factory()->create(['tenant_code' => 'RLS_HIDE_B']);

        TenantContext::getInstance()->setTenantId($tenantA->tenant_id);
        TenantWallet::create([
            'wallet_id' => (string) Str::uuid(),
            'tenant_id' => $tenantA->tenant_id,
            'balance'   => 10,
            'status'    => 1,
        ]);

        TenantContext::getInstance()->setTenantId($tenantB->tenant_id);
        TenantWallet::create([
            'wallet_id' => (string) Str::uuid(),
            'tenant_id' => $tenantB->tenant_id,
            'balance'   => 20,
            'status'    => 1,
        ]);

        TenantContext::getInstance()->setTenantId($tenantA->tenant_id);
        $visible = TenantWallet::query()->get();
        $this->assertCount(1, $visible);
        $this->assertSame($tenantA->tenant_id, $visible->first()->tenant_id);

        // Clear session – policy should yield zero rows when tenant setting is empty
        DB::statement("SELECT set_config('app.current_tenant_id', '', false)");
        TenantContext::resetInstance();
        $this->assertCount(0, TenantWallet::query()->get());
    }
}
