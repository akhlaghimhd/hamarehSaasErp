<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FinancialAccounting;

use App\Base\Context\TenantContext;
use App\Modules\FinancialAccounting\Application\Services\TaxReportingService;
use App\Modules\FinancialAccounting\Application\Services\VatCalculationService;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\MoodianSubmission;
use App\Modules\FinancialAccounting\Infrastructure\Models\TaxRateConfig;
use App\Modules\SaasPlatform\Models\Tenant;
use Database\Seeders\FinanceDemoCoaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaxReportingAndDemoSeedTest extends TestCase
{
    protected Tenant $tenant;

    protected string $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'FIN_RES']);
        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenant->tenant_id]);
        $this->companyId = (string) Str::uuid();
    }

    #[Test]
    public function demo_coa_seeder_is_idempotent(): void
    {
        (new FinanceDemoCoaSeeder())->run();
        $count1 = Account::query()->where('tenant_id', $this->tenant->tenant_id)->count();
        $this->assertGreaterThanOrEqual(10, $count1);

        (new FinanceDemoCoaSeeder())->run();
        $count2 = Account::query()->where('tenant_id', $this->tenant->tenant_id)->count();
        $this->assertSame($count1, $count2);

        $this->assertTrue(
            TaxRateConfig::query()->where('tax_code', 'VAT_STD')->exists()
        );
        $this->assertTrue(
            Account::query()->where('account_code', '1101')->where('is_postable', true)->exists()
        );
    }

    #[Test]
    public function vat_summary_and_moodian_recon_work(): void
    {
        TaxRateConfig::create([
            'tax_rate_config_id' => (string) Str::uuid(),
            'tenant_id'          => $this->tenant->tenant_id,
            'tax_code'           => 'VAT_STD',
            'name'               => 'VAT',
            'rate_percent'       => 10,
            'valid_from'         => '2020-01-01',
            'is_default'         => true,
            'status'             => 1,
            'row_version'        => 1,
        ]);

        $vat = new VatCalculationService();
        $tx = $vat->recordTransaction([
            'company_id'           => $this->companyId,
            'source_document_type' => 'MANUAL_INVOICE',
            'source_document_id'   => (string) Str::uuid(),
            'tax_code'             => 'VAT_STD',
            'transaction_date'     => '2026-10-01',
            'net_amount'           => 1000,
            'direction'            => 'OUTPUT',
        ]);

        // second tx without moodian → gap
        $vat->recordTransaction([
            'company_id'           => $this->companyId,
            'source_document_type' => 'MANUAL_INVOICE',
            'source_document_id'   => (string) Str::uuid(),
            'tax_code'             => 'VAT_STD',
            'transaction_date'     => '2026-10-02',
            'net_amount'           => 500,
            'direction'            => 'OUTPUT',
        ]);

        MoodianSubmission::create([
            'moodian_submission_id' => (string) Str::uuid(),
            'tenant_id'             => $this->tenant->tenant_id,
            'company_id'            => $this->companyId,
            'source_document_type'  => $tx->source_document_type,
            'source_document_id'    => $tx->source_document_id,
            'tax_transaction_id'    => $tx->tax_transaction_id,
            'status'                => MoodianSubmission::STATUS_ACCEPTED,
            'row_version'           => 1,
        ]);

        $reports = new TaxReportingService();
        $summary = $reports->vatPeriodSummary($this->companyId, '2026-10-01', '2026-10-31');
        $this->assertSame(2, $summary['totals']['txn_count']);
        $this->assertSame('150.0000', $summary['totals']['output_tax']);

        $recon = $reports->moodianLedgerRecon($this->companyId, '2026-10-01', '2026-10-31');
        $this->assertSame(2, $recon['tax_txn_count']);
        $this->assertSame(1, $recon['gap_count']);
        $this->assertSame('MISSING_MOODIAN_SUBMISSION', $recon['gaps'][0]['reason']);
    }
}
