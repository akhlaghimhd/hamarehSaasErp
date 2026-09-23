<?php

namespace Tests\Feature\Modules\Organization;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\Organization\Services\CompanyService;
use App\Modules\Organization\Services\ConsolidationRunService;
use App\Modules\Organization\Services\EnterpriseStructureConfigurator;
use App\Modules\Organization\Services\OrganizationEventPublisher;
use App\Modules\Organization\Services\OrgHierarchyService;
use App\Modules\Organization\DTOs\CreateCompanyDTO;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\ConsolidationRun;
use App\Modules\Organization\Models\OrgHierarchy;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use App\Base\Services\ScopeAccessGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class OrgP6ScopeConsolEscTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'ORG_P6',
            'status'      => 1,
        ]);

        $user = User::factory()->create(['status' => 1]);
        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $user->user_id,
            'status'    => 1,
        ]);

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

    #[Test]
    public function business_unit_is_registered_scope_type(): void
    {
        $types = config('scope.registered_scope_types', []);
        $this->assertContains('BUSINESS_UNIT', $types);

        // gradual: no BU scopes → canAccess still true
        $guard = new ScopeAccessGuard();
        $this->assertTrue($guard->canAccess('BUSINESS_UNIT', (string) Str::uuid()));
    }

    #[Test]
    public function consolidation_snapshot_publishes_outbox_event(): void
    {
        $co = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'C6', name: 'Consol Co'
        ));

        $hier = app(OrgHierarchyService::class)->createHierarchy(
            'LEGAL-C6', 'Legal C6', OrgHierarchy::PURPOSE_LEGAL
        );
        app(OrgHierarchyService::class)->addNode($hier->hierarchy_id, 'COMPANY', $co->company_id);

        $svc = app(ConsolidationRunService::class);
        $run = $svc->createDraft('CR-1', 'Run 1', $hier->hierarchy_id);
        $snap = $svc->snapshot($run->consol_run_id);

        $this->assertSame(ConsolidationRun::STATUS_SNAPSHOTTED, $snap->status);
        $this->assertNotEmpty($snap->snapshot_payload['nodes'] ?? []);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'  => $this->tenant->tenant_id,
            'event_type' => config('organization.events.consolidation_snapshotted'),
        ]);
    }

    #[Test]
    public function esc_applies_template_with_primary_and_legal_hierarchy(): void
    {
        $result = app(EnterpriseStructureConfigurator::class)->applyTemplate([
            'hq_name'                => 'ESC HQ',
            'hq_code'                => 'ESC-HQ',
            'create_legal_hierarchy' => true,
        ]);

        $this->assertNotEmpty($result['primary_company_id']);
        $this->assertNotEmpty($result['hierarchy_id']);

        $co = Company::find($result['primary_company_id']);
        $this->assertTrue((bool) $co->is_primary);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'  => $this->tenant->tenant_id,
            'event_type' => config('organization.events.structure_template_applied'),
        ]);
    }

    #[Test]
    public function elimination_event_can_be_published(): void
    {
        $co = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'EL6', name: 'Elim Co', entityKind: 'ELIMINATION'
        ));

        // ELIMINATION requires parent — create parent first path
        $parent = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'PAR6', name: 'Parent'
        ));
        $elim = app(CompanyService::class)->createCompany(new CreateCompanyDTO(
            code: 'EL6B',
            name: 'Elim',
            parentCompanyId: $parent->company_id,
            entityKind: 'ELIMINATION',
        ));

        $eventId = app(OrganizationEventPublisher::class)
            ->publishEliminationRequested($elim->company_id, ['reason' => 'test']);

        $this->assertNotEmpty($eventId);
        $this->assertTrue(
            DB::table('event_outbox')->where('event_id', $eventId)->exists()
        );
    }
}
