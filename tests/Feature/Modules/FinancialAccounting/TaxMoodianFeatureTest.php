<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FinancialAccounting;

use App\Base\Context\TenantContext;
use App\Modules\FinancialAccounting\Application\Services\FinanceComplianceAlertService;
use App\Modules\FinancialAccounting\Application\Services\MoodianSubmissionService;
use App\Modules\FinancialAccounting\Application\Services\VatCalculationService;
use App\Modules\FinancialAccounting\Infrastructure\Models\MoodianSubmission;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaxMoodianFeatureTest extends TestCase
{
    protected Tenant $tenant;

    protected string $companyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['tenant_code' => 'FIN_P2']);
        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        $this->companyId = (string) Str::uuid();
    }

    #[Test]
    public function vat_split_uses_configurable_rate(): void
    {
        $vat = new VatCalculationService();
        $vat->upsertRate([
            'tax_code'     => 'VAT_STD',
            'name'         => 'ارزش افزوده استاندارد',
            'rate_percent' => 10,
            'valid_from'   => '2020-01-01',
            'is_default'   => true,
        ]);

        $split = $vat->splitNet(1000, 'VAT_STD', now()->toDateString());
        $this->assertEquals(100.0, (float) $split['tax']);
        $this->assertEquals(1100.0, (float) $split['gross']);

        $txn = $vat->recordTransaction([
            'company_id'           => $this->companyId,
            'source_document_type' => 'MANUAL_INVOICE',
            'source_document_id'   => (string) Str::uuid(),
            'tax_code'             => 'VAT_STD',
            'net_amount'           => 1000,
            'transaction_date'     => now()->toDateString(),
            'direction'            => 'OUTPUT',
        ]);

        $this->assertEquals(100.0, (float) $txn->tax_amount);
    }

    #[Test]
    public function moodian_mock_submit_and_poll_accepted(): void
    {
        $svc = new MoodianSubmissionService();
        $docId = (string) Str::uuid();

        $row = $svc->submit([
            'company_id'           => $this->companyId,
            'source_document_type' => 'MANUAL_INVOICE',
            'source_document_id'   => $docId,
        ], ['amount' => 1100, 'tax' => 100]);

        $this->assertNotNull($row->external_ref);
        $this->assertSame(MoodianSubmission::STATUS_SUBMITTED, $row->status);

        $polled = $svc->poll($row->moodian_submission_id);
        $this->assertSame(MoodianSubmission::STATUS_ACCEPTED, $polled->status);
    }

    #[Test]
    public function compliance_scan_raises_moodian_missing(): void
    {
        $vat = new VatCalculationService();
        $vat->upsertRate([
            'tax_code'     => 'VAT_STD',
            'name'         => 'VAT',
            'rate_percent' => 9,
            'valid_from'   => '2020-01-01',
        ]);

        $vat->recordTransaction([
            'company_id'           => $this->companyId,
            'source_document_type' => 'MANUAL_INVOICE',
            'source_document_id'   => (string) Str::uuid(),
            'tax_code'             => 'VAT_STD',
            'net_amount'           => 500,
            'transaction_date'     => now()->toDateString(),
        ]);

        $alerts = new FinanceComplianceAlertService();
        $n = $alerts->scanMissingMoodian($this->companyId);
        $this->assertGreaterThanOrEqual(1, $n);
        $this->assertTrue($alerts->listOpen($this->companyId)->isNotEmpty());
    }
}
