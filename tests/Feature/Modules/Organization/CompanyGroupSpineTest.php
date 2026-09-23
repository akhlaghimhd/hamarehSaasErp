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
use App\Modules\Organization\DTOs\CreateCompanyDTO;
use App\Modules\Organization\DTOs\UpdateCompanyDTO;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * ORG-P1-01 … ORG-P1-06 — Group spine rules
 */
class CompanyGroupSpineTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'ORG_P1',
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
            'code'      => 'org-p1',
            'name'      => 'Org P1',
            'status'    => 1,
        ]);

        foreach ([
            'organization.company.view',
            'organization.company.create',
            'organization.company.update',
            'organization.company.delete',
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
            'org-p1',
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
    public function first_company_becomes_primary_operating(): void
    {
        $response = $this->withHeaders($this->headers())->postJson('/api/organization/companies', [
            'code' => 'HQ',
            'name' => 'Headquarters',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_primary', true)
            ->assertJsonPath('data.entity_kind', 'OPERATING');

        $this->assertDatabaseHas('erp_companies', [
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'HQ',
            'is_primary' => true,
            'entity_kind'=> 'OPERATING',
        ]);
    }

    #[Test]
    public function only_one_primary_per_tenant(): void
    {
        $this->withHeaders($this->headers())->postJson('/api/organization/companies', [
            'code' => 'A',
            'name' => 'Company A',
        ])->assertStatus(201);

        $b = $this->withHeaders($this->headers())->postJson('/api/organization/companies', [
            'code'       => 'B',
            'name'       => 'Company B',
            'is_primary' => true,
        ]);
        $b->assertStatus(201)->assertJsonPath('data.is_primary', true);

        $primaries = Company::where('tenant_id', $this->tenant->tenant_id)
            ->where('is_primary', true)
            ->count();
        $this->assertSame(1, $primaries);

        $this->assertDatabaseHas('erp_companies', [
            'code'       => 'B',
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('erp_companies', [
            'code'       => 'A',
            'is_primary' => false,
        ]);
    }

    #[Test]
    public function parent_must_be_same_tenant_and_cycle_is_rejected(): void
    {
        $service = app(CompanyService::class);

        $parent = $service->createCompany(new CreateCompanyDTO(
            code: 'P',
            name: 'Parent',
            isPrimary: true,
        ));

        $child = $service->createCompany(new CreateCompanyDTO(
            code: 'C',
            name: 'Child',
            parentCompanyId: $parent->company_id,
        ));

        $this->assertSame($parent->company_id, $child->parent_company_id);

        $this->expectException(\Exception::class);
        $service->updateCompany($parent->company_id, new UpdateCompanyDTO(
            code: 'P',
            name: 'Parent',
            isPrimary: true,
            parentCompanyId: $child->company_id,
        ));
    }

    #[Test]
    public function elimination_requires_parent(): void
    {
        $service = app(CompanyService::class);

        $this->expectException(\Exception::class);
        $service->createCompany(new CreateCompanyDTO(
            code: 'ELIM',
            name: 'Elim Co',
            entityKind: 'ELIMINATION',
        ));
    }

    #[Test]
    public function ensure_primary_company_for_tenant_is_idempotent(): void
    {
        $service = app(CompanyService::class);

        $first = $service->ensurePrimaryCompanyForTenant(
            $this->tenant->tenant_id,
            'Onboard HQ',
            'HQ'
        );

        $second = $service->ensurePrimaryCompanyForTenant($this->tenant->tenant_id);

        $this->assertSame($first->company_id, $second->company_id);
        $this->assertTrue((bool) $second->is_primary);
        $this->assertSame('OPERATING', $second->entity_kind);

        $count = Company::where('tenant_id', $this->tenant->tenant_id)->count();
        $this->assertSame(1, $count);
    }
}
