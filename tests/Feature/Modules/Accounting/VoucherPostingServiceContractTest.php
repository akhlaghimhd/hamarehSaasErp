<?php

namespace Tests\Feature\Modules\Accounting;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\Accounting\Services\VoucherPostingService;
use App\Base\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-ACC-04 (lightweight) — Inter-module contract for VoucherPostingService.
 * Other modules must call this Service in-process (no HTTP, no Physical FK).
 * Simulates Inventory/Sales calling Accounting without those modules existing yet.
 */
class VoucherPostingServiceContractTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $accountDebitId;
    protected string $accountCreditId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'POST_CTR', 'status' => 1]);
        $this->user = User::factory()->create(['status' => 1]);

        Context::add('tenant_id', $this->tenant->tenant_id);
        Context::add('user_id', $this->user->user_id);
        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);

        $this->accountDebitId  = (string) Str::uuid();
        $this->accountCreditId = (string) Str::uuid();

        DB::table('fin_accounts')->insert([
            [
                'account_id'   => $this->accountDebitId,
                'tenant_id'    => $this->tenant->tenant_id,
                'code'         => '1200',
                'name'         => 'Inventory Asset',
                'account_type' => 1,
                'level'        => 1,
                'is_active'    => true,
                'created_at'   => now(),
                'row_version'  => 1,
            ],
            [
                'account_id'   => $this->accountCreditId,
                'tenant_id'    => $this->tenant->tenant_id,
                'code'         => '2100',
                'name'         => 'GR/IR Clearing',
                'account_type' => 2,
                'level'        => 1,
                'is_active'    => true,
                'created_at'   => now(),
                'row_version'  => 1,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::resetInstance();
        parent::tearDown();
    }

    #[Test]
    public function external_module_can_post_balanced_voucher_via_service_contract(): void
    {
        /** @var VoucherPostingService $service */
        $service = app(VoucherPostingService::class);

        $header = [
            'voucher_date'       => '2026-04-01',
            'description'        => 'Goods receipt from PO-100',
            'reference_number'   => 'INV-GR-100',
            'status'             => 2,
            'source_module'      => 'inventory',
            'source_document_id' => (string) Str::uuid(),
        ];

        $lines = [
            [
                'account_id'  => $this->accountDebitId,
                'debit'       => 2500.0000,
                'credit'      => 0,
                'description' => 'Inventory increase',
            ],
            [
                'account_id'  => $this->accountCreditId,
                'debit'       => 0,
                'credit'      => 2500.0000,
                'description' => 'GR/IR clearing',
            ],
        ];

        $voucherId = $service->postVoucher($header, $lines);

        $this->assertNotEmpty($voucherId);

        $this->assertDatabaseHas('fin_vouchers', [
            'voucher_id'       => $voucherId,
            'tenant_id'        => $this->tenant->tenant_id,
            'reference_number' => 'INV-GR-100',
            'total_amount'     => 2500.0000,
            'status'           => 2,
        ]);

        $this->assertDatabaseHas('fin_voucher_items', [
            'voucher_id' => $voucherId,
            'account_id' => $this->accountDebitId,
            'debit'      => 2500.0000,
        ]);
        $this->assertDatabaseHas('fin_voucher_items', [
            'voucher_id' => $voucherId,
            'account_id' => $this->accountCreditId,
            'credit'     => 2500.0000,
        ]);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'      => $this->tenant->tenant_id,
            'aggregate_type' => 'fin_vouchers',
            'aggregate_id'   => $voucherId,
            'event_type'     => 'accounting.voucher.posted.v1',
        ]);
    }

    #[Test]
    public function unbalanced_lines_are_rejected_by_contract(): void
    {
        $service = app(VoucherPostingService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not balanced');

        $service->postVoucher(
            [
                'voucher_date'     => '2026-04-01',
                'description'      => 'Unbalanced attempt',
                'reference_number' => 'BAD-1',
                'source_module'    => 'inventory',
            ],
            [
                [
                    'account_id' => $this->accountDebitId,
                    'debit'      => 100,
                    'credit'     => 0,
                ],
            ]
        );
    }

    #[Test]
    public function empty_lines_are_rejected_by_contract(): void
    {
        $service = app(VoucherPostingService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one line');

        $service->postVoucher(
            [
                'voucher_date'     => '2026-04-01',
                'description'      => 'Empty attempt',
                'reference_number' => 'BAD-2',
                'source_module'    => 'sales',
            ],
            []
        );
    }

    #[Test]
    public function reverse_voucher_creates_opposite_entries(): void
    {
        $service = app(VoucherPostingService::class);

        $originalId = $service->postVoucher(
            [
                'voucher_date'     => '2026-04-01',
                'description'      => 'Original GR',
                'reference_number' => 'ORIG-1',
                'status'           => 2,
                'source_module'    => 'inventory',
            ],
            [
                ['account_id' => $this->accountDebitId, 'debit' => 100, 'credit' => 0, 'description' => 'D'],
                ['account_id' => $this->accountCreditId, 'debit' => 0, 'credit' => 100, 'description' => 'C'],
            ]
        );

        $reversalId = $service->reverseVoucher($originalId, 'Cancel GR');

        $this->assertNotSame($originalId, $reversalId);

        $this->assertDatabaseHas('fin_vouchers', [
            'voucher_id' => $reversalId,
            'status'     => 2,
        ]);

        $this->assertDatabaseHas('fin_voucher_items', [
            'voucher_id' => $reversalId,
            'account_id' => $this->accountDebitId,
            'credit'     => 100.0000,
        ]);
        $this->assertDatabaseHas('fin_voucher_items', [
            'voucher_id' => $reversalId,
            'account_id' => $this->accountCreditId,
            'debit'      => 100.0000,
        ]);
    }
}
