<?php

namespace Tests\Unit\Contracts;

use App\Modules\Organization\Contracts\CompanyLookupContract;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Services\CompanyLookupService;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyLookupServiceTest extends TestCase
{
    private CompanyLookupService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CompanyLookupContract::class);

        $tenantId = (string) Str::uuid();
        Context::add('tenant_id', $tenantId);
        Context::add('user_id', (string) Str::uuid());
    }

    #[Test]
    public function it_is_bound_to_company_lookup_contract(): void
    {
        $this->assertInstanceOf(CompanyLookupService::class, $this->service);
    }

    #[Test]
    public function find_by_id_returns_active_company(): void
    {
        $company = Company::create([
            'tenant_id'  => Context::get('tenant_id'),
            'code'       => 'CMP-001',
            'name'       => 'Test Company',
            'is_active'  => true,
        ]);

        $found = $this->service->findById($company->company_id);

        $this->assertNotNull($found);
        $this->assertSame($company->company_id, $found->company_id);
        $this->assertSame('CMP-001', $found->code);
    }

    #[Test]
    public function exists_returns_true_for_active_company(): void
    {
        $company = Company::create([
            'tenant_id'  => Context::get('tenant_id'),
            'code'       => 'CMP-002',
            'name'       => 'Another Company',
            'is_active'  => true,
        ]);

        $this->assertTrue($this->service->exists($company->company_id));
    }

    #[Test]
    public function get_basic_info_returns_expected_structure(): void
    {
        $company = Company::create([
            'tenant_id'  => Context::get('tenant_id'),
            'code'       => 'CMP-003',
            'name'       => 'Info Company',
            'is_active'  => true,
        ]);

        $info = $this->service->getBasicInfo($company->company_id);

        $this->assertIsArray($info);
        $this->assertSame($company->company_id, $info['company_id']);
        $this->assertSame('CMP-003', $info['code']);
        $this->assertSame('Info Company', $info['name']);
        $this->assertTrue($info['is_active']);
    }
}
