<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FinancialAccounting;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Application\Services\ChequeService;
use App\Modules\FinancialAccounting\Application\Services\OpenItemService;
use App\Modules\FinancialAccounting\Application\Services\TreasuryService;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\Cheque;
use App\Modules\FinancialAccounting\Infrastructure\Models\Ledger;
use App\Modules\FinancialAccounting\Infrastructure\Models\OpenItem;
use App\Modules\FinancialAccounting\Infrastructure\Models\TreasuryDocument;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TreasuryArApFeatureTest extends TestCase
{
    protected Tenant $tenant;

    protected string $companyId;

    protected string $periodId;

    protected Ledger $ledger;

    protected Account $cashGl;

    protected Account $arGl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'FIN_P1']);
        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);

        $this->companyId = (string) Str::uuid();
        $this->periodId = (string) Str::uuid();

        $this->ledger = Ledger::create([
            'ledger_id'   => (string) Str::uuid(),
            'tenant_id'   => $this->tenant->tenant_id,
            'company_id'  => $this->companyId,
            'code'        => 'LG',
            'name'        => 'Leading',
            'is_leading'  => true,
            'status'      => 1,
            'row_version' => 1,
        ]);

        $this->cashGl = Account::create([
            'account_id'    => (string) Str::uuid(),
            'tenant_id'     => $this->tenant->tenant_id,
            'account_code'  => '1102',
            'name'          => 'بانک',
            'account_type'  => Account::TYPE_ASSET,
            'is_postable'   => true,
            'normal_balance'=> Account::BALANCE_DEBIT,
            'row_version'   => 1,
        ]);

        $this->arGl = Account::create([
            'account_id'    => (string) Str::uuid(),
            'tenant_id'     => $this->tenant->tenant_id,
            'account_code'  => '1103',
            'name'          => 'دریافتنی',
            'account_type'  => Account::TYPE_ASSET,
            'is_postable'   => true,
            'normal_balance'=> Account::BALANCE_DEBIT,
            'row_version'   => 1,
        ]);
    }

    #[Test]
    public function receipt_posts_gl_and_settles_ar_open_item(): void
    {
        $oi = new OpenItemService();
        $item = $oi->registerManualInvoice([
            'company_id'        => $this->companyId,
            'side'              => OpenItem::SIDE_AR,
            'document_date'     => now()->toDateString(),
            'due_date'          => now()->subDays(5)->toDateString(),
            'counterparty_name' => 'مشتری الف',
            'original_amount'   => 1000,
            'gl_account_id'     => $this->arGl->account_id,
        ]);

        $treasury = new TreasuryService();
        $cash = $treasury->createCashAccount([
            'company_id'    => $this->companyId,
            'gl_account_id' => $this->cashGl->account_id,
            'code'          => 'BANK1',
            'name'          => 'بانک ملی',
        ]);

        $doc = $treasury->createDocument([
            'company_id'                => $this->companyId,
            'period_id'                 => $this->periodId,
            'cash_account_id'           => $cash->cash_account_id,
            'document_type'             => TreasuryDocument::TYPE_RECEIPT,
            'document_date'             => now()->toDateString(),
            'amount'                    => 1000,
            'counterparty_open_item_id' => $item->open_item_id,
            'offset_account_id'         => $this->arGl->account_id,
            'ledger_id'                 => $this->ledger->ledger_id,
            'auto_post'                 => true,
        ]);

        $this->assertSame(TreasuryDocument::STATUS_POSTED, $doc->status);
        $this->assertNotNull($doc->journal_entry_id);

        $item = $item->fresh();
        $this->assertSame(OpenItem::STATUS_CLOSED, $item->status);
        $this->assertEquals(0.0, (float) $item->open_amount);

        $aging = $oi->aging($this->companyId, OpenItem::SIDE_AR);
        $openBuckets = collect($aging)->sum(fn ($b) => (float) $b['amount']);
        $this->assertEquals(0.0, $openBuckets);
    }

    #[Test]
    public function cheque_rejects_invalid_transition(): void
    {
        $svc = new ChequeService();
        $ch = $svc->create([
            'company_id'    => $this->companyId,
            'direction'     => Cheque::DIR_IN,
            'cheque_number' => '123',
            'due_date'      => now()->addDays(10)->toDateString(),
            'amount'        => 500,
        ]);

        $this->assertSame(Cheque::STATUS_RECEIVED, $ch->status);

        $this->expectException(DomainException::class);
        $svc->transition($ch->cheque_id, Cheque::STATUS_CLEARED);
    }

    #[Test]
    public function cheque_deposit_then_clear(): void
    {
        $svc = new ChequeService();
        $ch = $svc->create([
            'company_id'    => $this->companyId,
            'direction'     => Cheque::DIR_IN,
            'cheque_number' => '456',
            'due_date'      => now()->addDays(3)->toDateString(),
            'amount'        => 200,
        ]);

        $ch = $svc->transition($ch->cheque_id, Cheque::STATUS_DEPOSITED);
        $ch = $svc->transition($ch->cheque_id, Cheque::STATUS_CLEARED);
        $this->assertSame(Cheque::STATUS_CLEARED, $ch->status);
    }
}
