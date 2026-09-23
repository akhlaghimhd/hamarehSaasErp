<?php

namespace Tests\Feature\Modules\Organization;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Services\CompanyService;
use App\Modules\Organization\Services\CompanyFiscalAssignmentService;
use App\Modules\Organization\DTOs\CreateCompanyDTO;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * ORG-P2-01 … ORG-P2-04 — Financial attributes + FY contract boundary
 */
class CompanyFinancialAttrsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'ORG_P2',
            'status'      => 1,
        ]);

        $this->user = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->user->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'org-p2',
            'name'      => 'Org P2',
            'status'    => 1,
        ]);

        foreach ([
            'organization.company.view',
            'organization.company.create',
            'organization.company.update',
        ] as $code) {
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenant->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'Organization',
                'action_type'          => strtoupper(explode('.', $code)[2] ?? 'VIEW'),
                'status'               => 1,
            ]);
            TenantRolePermission::create([
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id'                 => $this->tenant->tenant_id,
                'tenant_role_id'            => $role->tenant_role_id,
                'tenant_permission_id'      => $perm->tenant_permission_id,
            ]);
        }

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenant->tenant_id,
            'user_id'             => $this->user->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        $this->token = $this->user->createToken(
            'org-p2',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);
        ScopeContext::resetInstance();
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ];
    }

    #[Test]
    public function company_stores_base_currency_coa_and_rate_type(): void
    {
        $currencyId = (string) Str::uuid();
        $coaId = (string) Str::uuid();

        $response = $this->withHeaders($this->headers())->postJson('/api/organization/companies', [
            'code'                     => 'FIN-01',
            'name'                     => 'Financial Co',
            'base_currency_id'         => $currencyId,
            'chart_of_accounts_id'     => $coaId,
            'default_consol_rate_type' => 'AVERAGE',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.base_currency_id', $currencyId)
            ->assertJsonPath('data.chart_of_accounts_id', $coaId)
            ->assertJsonPath('data.default_consol_rate_type', 'AVERAGE');

        $this->assertDatabaseHas('erp_companies', [
            'code'                     => 'FIN-01',
            'base_currency_id'         => $currencyId,
            'chart_of_accounts_id'     => $coaId,
            'default_consol_rate_type' => 'AVERAGE',
        ]);
    }

    #[Test]
    public function fiscal_assignment_links_company_to_period_same_tenant(): void
    {
        $service = app(CompanyService::class);
        $company = $service->createCompany(new CreateCompanyDTO(
            code: 'FY-CO',
            name: 'FY Company',
        ));

        $periodId = (string) Str::uuid();
        DB::table('fin_fiscal_periods')->insert([
            'period_id'  => $periodId,
            'tenant_id'  => $this->tenant->tenant_id,
            'name'       => 'FY2026',
            'start_date' => '2026-01-01',
            'end_date'   => '2026-12-31',
            'is_closed'  => false,
            'created_at' => now(),
            'row_version'=> 1,
        ]);

        $fyService = app(CompanyFiscalAssignmentService::class);
        $assignment = $fyService->assign($company->company_id, $periodId, true);

        $this->assertTrue((bool) $assignment->is_primary);
        $this->assertSame($periodId, $assignment->period_id);

        $list = $fyService->listForCompany($company->company_id);
        $this->assertCount(1, $list);
    }

    #[Test]
    public function fiscal_assignment_rejects_foreign_tenant_period(): void
    {
        $service = app(CompanyService::class);
        $company = $service->createCompany(new CreateCompanyDTO(
            code: 'FY-BAD',
            name: 'FY Bad',
        ));

        $otherTenant = Tenant::factory()->create(['tenant_code' => 'OTHER_P2', 'status' => 1]);
        $periodId = (string) Str::uuid();
        DB::table('fin_fiscal_periods')->insert([
            'period_id'  => $periodId,
            'tenant_id'  => $otherTenant->tenant_id,
            'name'       => 'Other FY',
            'start_date' => '2026-01-01',
            'end_date'   => '2026-12-31',
            'is_closed'  => false,
            'created_at' => now(),
            'row_version'=> 1,
        ]);

        $this->expectException(\Exception::class);
        app(CompanyFiscalAssignmentService::class)->assign($company->company_id, $periodId);
    }

    #[Test]
    public function elimination_currency_must_match_parent_when_both_set(): void
    {
        $service = app(CompanyService::class);
        $currencyA = (string) Str::uuid();
        $currencyB = (string) Str::uuid();

        $parent = $service->createCompany(new CreateCompanyDTO(
            code: 'PAR',
            name: 'Parent',
            baseCurrencyId: $currencyA,
        ));

        $this->expectException(\Exception::class);
        $service->createCompany(new CreateCompanyDTO(
            code: 'EL',
            name: 'Elim',
            parentCompanyId: $parent->company_id,
            entityKind: 'ELIMINATION',
            baseCurrencyId: $currencyB,
        ));
    }
}
