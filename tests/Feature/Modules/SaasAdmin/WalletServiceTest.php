<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Modules\SaasAdmin\Models\Tenant;
use App\Modules\SaasAdmin\Models\TenantWallet;
use App\Modules\SaasAdmin\Models\TenantWalletTransaction;
use App\Modules\SaasAdmin\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $walletService;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = app(WalletService::class);
        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'WALLET_TEST',
            'status'      => 1,
        ]);
    }

    public function test_get_or_create_wallet(): void
    {
        $wallet = $this->walletService->getOrCreateWallet($this->tenant->tenant_id);

        $this->assertDatabaseHas('tenant_wallets', [
            'wallet_id' => $wallet->wallet_id,
            'tenant_id' => $this->tenant->tenant_id,
            'balance'   => 0,
            'status'    => 1,
        ]);
    }

    public function test_credit_increases_balance(): void
    {
        $tx = $this->walletService->credit($this->tenant->tenant_id, 100.5000, 'Initial credit');

        $this->assertEquals(WalletService::TYPE_CREDIT, $tx->transaction_type);
        $this->assertEquals(100.5000, $tx->amount);
        $this->assertEquals(100.5000, $tx->balance_after);

        $this->assertEquals(100.5000, $this->walletService->getBalance($this->tenant->tenant_id));
    }

    public function test_debit_decreases_balance(): void
    {
        $this->walletService->credit($this->tenant->tenant_id, 200.0000);
        $tx = $this->walletService->debit($this->tenant->tenant_id, 50.2500, 'Payment');

        $this->assertEquals(WalletService::TYPE_DEBIT, $tx->transaction_type);
        $this->assertEquals(149.7500, $tx->balance_after);
        $this->assertEquals(149.7500, $this->walletService->getBalance($this->tenant->tenant_id));
    }

    public function test_debit_throws_on_insufficient_balance(): void
    {
        $this->walletService->credit($this->tenant->tenant_id, 10.0000);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient wallet balance.');

        $this->walletService->debit($this->tenant->tenant_id, 50.0000);
    }
}