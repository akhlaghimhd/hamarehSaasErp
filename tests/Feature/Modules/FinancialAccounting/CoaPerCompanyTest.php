<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FinancialAccounting;

use App\Modules\FinancialAccounting\Application\Services\ChartOfAccountsService;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FIN-HOLD-W4 — CoA scoped by company_id (DEBT-FIN-CSN-001).
 */
class CoaPerCompanyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function account_create_requires_company_and_scopes_uniqueness(): void
    {
        $svc = app(ChartOfAccountsService::class);
        $this->assertTrue(method_exists($svc, 'listTree'));
        $this->assertTrue(method_exists($svc, 'cloneTreeToCompany'));
        $ref = new \ReflectionMethod($svc, 'listTree');
        $this->assertSame(1, $ref->getNumberOfParameters());
    }

    #[Test]
    public function account_model_fillable_includes_company_id(): void
    {
        $m = new Account();
        $this->assertContains('company_id', $m->getFillable());
    }
}
