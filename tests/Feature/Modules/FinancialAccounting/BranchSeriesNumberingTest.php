<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FinancialAccounting;

use App\Base\Context\TenantContext;
use App\Modules\FinancialAccounting\Application\Services\CompanyNumberingService;
use App\Modules\FinancialAccounting\Application\Services\FinanceCompanyScope;
use App\Modules\Organization\Models\Company;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FIN-HOLD-W3 / ADR-FIN-003 L07 — branch series optional from fiscal year start.
 */
class BranchSeriesNumberingTest extends TestCase
{
    protected Tenant $tenant;

    protected string $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'FIN_W3_BR']);
        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);

        $this->companyId = (string) Str::uuid();
        DB::table('erp_companies')->insert([
            'company_id'  => $this->companyId,
            'tenant_id'   => $this->tenant->tenant_id,
            'code'        => 'W3A',
            'name'        => 'W3 Company',
            'legal_name'  => 'W3 Company',
            'is_active'   => true,
            'is_primary'  => true,
            'entity_kind' => Company::ENTITY_KIND_OPERATING,
            'status'      => 1,
            'row_version' => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    protected function numberingService(): CompanyNumberingService
    {
        $scope = new class extends FinanceCompanyScope {
            public function assertCanReadCompany(string $companyId): void
            {
            }

            public function assertCanOperateCompany(string $companyId): void
            {
            }
        };

        return new CompanyNumberingService($scope);
    }

    #[Test]
    public function default_mode_does_not_use_branch_series(): void
    {
        $svc = $this->numberingService();
        $this->assertFalse(
            $svc->shouldUseBranchSeries($this->companyId, '1404', (string) Str::uuid())
        );
    }

    #[Test]
    public function enable_branch_series_applies_from_fiscal_year_key(): void
    {
        $svc = $this->numberingService();
        $svc->enableBranchSeries($this->companyId, '1404');

        $branchId = (string) Str::uuid();
        $this->assertFalse($svc->shouldUseBranchSeries($this->companyId, '1403', $branchId));
        $this->assertTrue($svc->shouldUseBranchSeries($this->companyId, '1404', $branchId));
        $this->assertTrue($svc->shouldUseBranchSeries($this->companyId, '1405', $branchId));
        $this->assertFalse($svc->shouldUseBranchSeries($this->companyId, '1405', null));
    }

    #[Test]
    public function disable_branch_series_stops_use(): void
    {
        $svc = $this->numberingService();
        $svc->enableBranchSeries($this->companyId, '1404');
        $svc->disableBranchSeries($this->companyId);

        $this->assertFalse(
            $svc->shouldUseBranchSeries($this->companyId, '1405', (string) Str::uuid())
        );
    }
}
