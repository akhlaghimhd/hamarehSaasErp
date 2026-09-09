<?php

namespace Tests\Feature\Modules\SaasPlatform;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\SaasPlatform\Models\TenantWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L1-10 – Smoke isolation checks for Layer 1 tables that carry tenant_id.
 * Assumes RLS policies from 2026_09_09_080001_enable_rls_on_saas_platform_tables
 * are applied when running against PostgreSQL with app.current_tenant_id set.
 */
class SaasPlatformRlsIsolationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tenant_wallets_belong_to_correct_tenant(): void
    {
        $tenantA = Tenant::factory()->create(['tenant_code' => 'RLS_A']);
        $tenantB = Tenant::factory()->create(['tenant_code' => 'RLS_B']);

        $walletA = TenantWallet::create([
            'wallet_id'  => (string) Str::uuid(),
            'tenant_id'  => $tenantA->tenant_id,
            'balance'    => 100,
            'status'     => 1,
        ]);

        $walletB = TenantWallet::create([
            'wallet_id'  => (string) Str::uuid(),
            'tenant_id'  => $tenantB->tenant_id,
            'balance'    => 200,
            'status'     => 1,
        ]);

        $this->assertSame($tenantA->tenant_id, $walletA->fresh()->tenant_id);
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
}
