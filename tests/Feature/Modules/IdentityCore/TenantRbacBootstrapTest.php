<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Base\Context\TenantContext;
use App\Modules\IdentityCore\Catalog\DefaultRoleCatalog;
use App\Modules\IdentityCore\Services\TenantRbacBootstrapService;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantRbacBootstrapTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'RBAC_BOOT',
            'status' => 1,
        ]);

        app()->instance('current_tenant_id', $this->tenant->tenant_id);
        app(TenantContext::class)->setTenantId($this->tenant->tenant_id);

        $this->seedIdentityPermissions();
    }

    #[Test]
    public function bootstrap_creates_system_roles_and_sod_when_roles_exist(): void
    {
        $svc = app(TenantRbacBootstrapService::class);
        $result = $svc->bootstrapTenant((string) $this->tenant->tenant_id, true);

        $this->assertGreaterThan(0, $result['roles_created'] + $result['roles_updated']);

        $identityManager = DB::table('tenant_roles')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->where('code', 'identity-manager')
            ->whereNull('deleted_at')
            ->first();
        $this->assertNotNull($identityManager);

        $viewer = DB::table('tenant_roles')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->where('code', 'viewer')
            ->whereNull('deleted_at')
            ->first();
        $this->assertNotNull($viewer);

        $result2 = $svc->bootstrapTenant((string) $this->tenant->tenant_id, false);
        $this->assertSame(0, $result2['roles_created']);
    }

    #[Test]
    public function bootstrap_skips_feature_gated_role_without_entitlement(): void
    {
        $svc = app(TenantRbacBootstrapService::class);
        $svc->bootstrapTenant((string) $this->tenant->tenant_id, true);

        $multi = DB::table('tenant_roles')
            ->where('tenant_id', $this->tenant->tenant_id)
            ->where('code', 'org-multi-company')
            ->whereNull('deleted_at')
            ->first();

        $this->assertNull($multi);
    }

    #[Test]
    public function catalog_sod_definitions_reference_existing_role_codes(): void
    {
        $roleCodes = array_column(DefaultRoleCatalog::roles(), 'code');
        $roleSet = array_flip($roleCodes);

        foreach (DefaultRoleCatalog::sodRules() as $rule) {
            $this->assertArrayHasKey($rule['role_a_code'], $roleSet, $rule['code'].' role_a missing');
            $this->assertArrayHasKey($rule['role_b_code'], $roleSet, $rule['code'].' role_b missing');
            $this->assertNotSame($rule['role_a_code'], $rule['role_b_code']);
            $this->assertContains($rule['enforcement'], ['BLOCK', 'WARN']);
            $this->assertGreaterThanOrEqual(1, $rule['severity']);
            $this->assertLessThanOrEqual(4, $rule['severity']);
        }
    }

    private function seedIdentityPermissions(): void
    {
        if (!Schema::hasTable('tenant_permissions')) {
            $this->markTestSkipped('tenant_permissions missing');
        }

        $perms = [
            'identity.user.view', 'identity.user.create', 'identity.user.update', 'identity.user.delete',
            'identity.role.view', 'identity.role.create', 'identity.role.update', 'identity.role.delete',
            'identity.role.assign', 'identity.role.assign-permissions',
            'identity.permission.view',
            'identity.scope.view', 'identity.scope.create', 'identity.scope.update', 'identity.scope.delete', 'identity.scope.assign',
            'identity.profile.view', 'identity.profile.update',
            'identity.membership_history.view',
            'identity.sod.view', 'identity.sod.manage',
            'identity.mfa.manage',
            'identity.access_cert.view', 'identity.access_cert.manage', 'identity.access_cert.certify',
            'organization.company.view', 'organization.company.create', 'organization.company.update', 'organization.company.delete',
            'organization.branch.view', 'organization.branch.create', 'organization.branch.update', 'organization.branch.delete',
            'organization.department.view', 'organization.department.create', 'organization.department.update', 'organization.department.delete',
            'organization.hierarchy.view', 'organization.hierarchy.manage',
            'organization.business_unit.view', 'organization.business_unit.manage',
            'organization.cost_center.view', 'organization.cost_center.manage',
            'organization.ownership.view', 'organization.ownership.manage',
            'organization.fiscal.view', 'organization.fiscal.manage',
            'organization.bank.view', 'organization.bank.manage',
            'organization.officer.view', 'organization.officer.manage',
            'organization.sales_org.view', 'organization.sales_org.manage',
            'organization.purch_org.view', 'organization.purch_org.manage',
            'organization.sales_structure.view', 'organization.sales_structure.manage',
            'organization.intercompany.view', 'organization.intercompany.manage',
            'organization.structure.configure',
            'organization.consolidation.view', 'organization.consolidation.manage',
        ];

        foreach ($perms as $code) {
            DB::table('tenant_permissions')->insert([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->tenant_id,
                'code' => $code,
                'name' => $code,
                'module_name' => explode('.', $code)[0],
                'action_type' => 'EXECUTE',
                'description' => $code,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
