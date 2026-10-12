<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FinancialAccounting;

use App\Base\Context\TenantContext;
use App\Modules\FinancialAccounting\Application\Services\LedgerService;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** ADR-FIN-002 F2-01 — soft-path (group-wide) ledger create/list. */
class LedgerServiceScopeTest extends TestCase
{
    #[Test]
    public function create_and_list_leading_ledger_for_company(): void
    {
        $tenant = Tenant::factory()->create(['tenant_code' => 'FIN_LED']);
        TenantContext::getInstance()->setTenantId($tenant->tenant_id);

        $companyId = (string) Str::uuid();
        $svc = new LedgerService();

        $led = $svc->ensureLeading($companyId, 'LG', 'دفتر اصلی');
        $this->assertTrue((bool) $led->is_leading);
        $this->assertSame($companyId, $led->company_id);

        $again = $svc->ensureLeading($companyId);
        $this->assertSame($led->ledger_id, $again->ledger_id);

        $list = $svc->list($companyId);
        $this->assertCount(1, $list);
    }
}
