<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FinancialAccounting;

use App\Base\Context\ScopeContext;
use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Application\Services\ChequeService;
use App\Modules\Organization\Models\Company;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChequeCompanyScopeTest extends TestCase
{
    #[Test]
    public function scoped_actor_cannot_create_cheque_on_sibling_company(): void
    {
        $tenant = Tenant::factory()->create(['tenant_code' => 'FIN_CHQ']);
        TenantContext::getInstance()->setTenantId($tenant->tenant_id);

        $companyA = (string) Str::uuid();
        $companyB = (string) Str::uuid();

        DB::table('erp_companies')->insert([
            [
                'company_id'  => $companyA,
                'tenant_id'   => $tenant->tenant_id,
                'code'        => 'CA',
                'name'        => 'A',
                'legal_name'  => 'A',
                'is_active'   => true,
                'is_primary'  => true,
                'entity_kind' => Company::ENTITY_KIND_OPERATING,
                'status'      => 1,
                'row_version' => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'company_id'  => $companyB,
                'tenant_id'   => $tenant->tenant_id,
                'code'        => 'CB',
                'name'        => 'B',
                'legal_name'  => 'B',
                'is_active'   => true,
                'is_primary'  => false,
                'entity_kind' => Company::ENTITY_KIND_OPERATING,
                'status'      => 1,
                'row_version' => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);

        Context::add('security_context', [
            'is_owner'  => false,
            'tenant_id' => $tenant->tenant_id,
            'user_id'   => (string) Str::uuid(),
        ]);
        ScopeContext::resetInstance();
        ScopeContext::getInstance()->setScopes([
            [
                'scope_id'     => (string) Str::uuid(),
                'scope_type'   => 'COMPANY',
                'reference_id' => $companyA,
                'scope_name'   => 'A',
            ],
        ]);

        $svc = new ChequeService();

        $ok = $svc->create([
            'company_id'    => $companyA,
            'direction'     => 'IN',
            'cheque_number' => '1001',
            'due_date'      => '2026-12-01',
            'amount'        => 1000,
        ]);
        $this->assertSame($companyA, $ok->company_id);

        $this->expectException(DomainException::class);
        $svc->create([
            'company_id'    => $companyB,
            'direction'     => 'IN',
            'cheque_number' => '1002',
            'due_date'      => '2026-12-01',
            'amount'        => 500,
        ]);
    }
}
