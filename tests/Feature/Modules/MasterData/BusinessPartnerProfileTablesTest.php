<?php

namespace Tests\Feature\Modules\MasterData;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\MasterData\Models\BusinessPartner;
use App\Modules\MasterData\Models\Person;
use App\Modules\MasterData\Models\BusinessPartnerOrganization;
use App\Modules\MasterData\Models\BusinessPartnerRole;
use App\Modules\MasterData\Models\BusinessPartnerContact;
use App\Modules\MasterData\Models\BusinessPartnerIdentification;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L5 – SoftDeletes + Tenant Isolation for Business Partner profile tables
 * Tables: persons, business_partner_organizations, business_partner_roles,
 *         business_partner_contacts, business_partner_identifications
 */
class BusinessPartnerProfileTablesTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected BusinessPartner $partnerA;
    protected BusinessPartner $partnerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'BP_PROF_A',
            'status' => 1,
        ]);

        $this->tenantB = Tenant::factory()->create([
            'tenant_code' => 'BP_PROF_B',
            'status' => 1,
        ]);

        $this->partnerA = BusinessPartner::withoutGlobalScopes()->create([
            'business_partner_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'code' => 'BP-A-01',
            'display_name' => 'Partner A',
            'partner_type' => 1,
            'status' => 1,
        ]);

        $this->partnerB = BusinessPartner::withoutGlobalScopes()->create([
            'business_partner_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->tenant_id,
            'code' => 'BP-B-01',
            'display_name' => 'Partner B',
            'partner_type' => 2,
            'status' => 1,
        ]);

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    #[Test]
    public function person_can_be_created_soft_deleted_and_is_tenant_scoped(): void
    {
        $person = Person::create([
            'tenant_id' => $this->tenantA->tenant_id,
            'business_partner_id' => $this->partnerA->business_partner_id,
            'first_name' => 'Ali',
            'last_name' => 'Rezaei',
            'national_code' => '0012345678',
            'status' => 1,
        ]);

        $this->assertDatabaseHas('persons', [
            'person_id' => $person->person_id,
            'tenant_id' => $this->tenantA->tenant_id,
            'first_name' => 'Ali',
        ]);

        // Soft delete
        $person->delete();
        $this->assertSoftDeleted('persons', ['person_id' => $person->person_id]);

        // Tenant isolation: tenant A scope must not see tenant B person
        $personB = Person::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->tenant_id,
            'business_partner_id' => $this->partnerB->business_partner_id,
            'first_name' => 'Sara',
            'last_name' => 'Karimi',
            'status' => 1,
        ]);

        $ids = Person::pluck('person_id')->toArray();
        $this->assertNotContains($personB->person_id, $ids);
    }

    #[Test]
    public function organization_profile_supports_soft_delete_and_isolation(): void
    {
        $org = BusinessPartnerOrganization::create([
            'tenant_id' => $this->tenantA->tenant_id,
            'business_partner_id' => $this->partnerA->business_partner_id,
            'legal_name' => 'Acme Co',
            'registration_number' => 'REG-100',
            'status' => 1,
        ]);

        $this->assertDatabaseHas('business_partner_organizations', [
            'business_partner_organization_id' => $org->business_partner_organization_id,
            'tenant_id' => $this->tenantA->tenant_id,
        ]);

        $org->delete();
        $this->assertSoftDeleted('business_partner_organizations', [
            'business_partner_organization_id' => $org->business_partner_organization_id,
        ]);

        $orgB = BusinessPartnerOrganization::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->tenant_id,
            'business_partner_id' => $this->partnerB->business_partner_id,
            'legal_name' => 'Other Co',
            'status' => 1,
        ]);

        $ids = BusinessPartnerOrganization::pluck('business_partner_organization_id')->toArray();
        $this->assertNotContains($orgB->business_partner_organization_id, $ids);
    }

    #[Test]
    public function role_contact_and_identification_support_soft_delete_and_isolation(): void
    {
        $role = BusinessPartnerRole::create([
            'tenant_id' => $this->tenantA->tenant_id,
            'business_partner_id' => $this->partnerA->business_partner_id,
            'role_type' => 1,
            'role_code' => 'CUSTOMER',
            'status' => 1,
        ]);

        $contact = BusinessPartnerContact::create([
            'tenant_id' => $this->tenantA->tenant_id,
            'business_partner_id' => $this->partnerA->business_partner_id,
            'contact_type' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'is_primary' => true,
            'status' => 1,
        ]);

        $ident = BusinessPartnerIdentification::create([
            'tenant_id' => $this->tenantA->tenant_id,
            'business_partner_id' => $this->partnerA->business_partner_id,
            'identification_type_code' => 'ECONOMIC_CODE',
            'id_number' => 'EC-999',
            'status' => 1,
        ]);

        $this->assertDatabaseHas('business_partner_roles', ['business_partner_role_id' => $role->business_partner_role_id]);
        $this->assertDatabaseHas('business_partner_contacts', ['business_partner_contact_id' => $contact->business_partner_contact_id]);
        $this->assertDatabaseHas('business_partner_identifications', ['bp_identification_id' => $ident->bp_identification_id]);

        $role->delete();
        $contact->delete();
        $ident->delete();

        $this->assertSoftDeleted('business_partner_roles', ['business_partner_role_id' => $role->business_partner_role_id]);
        $this->assertSoftDeleted('business_partner_contacts', ['business_partner_contact_id' => $contact->business_partner_contact_id]);
        $this->assertSoftDeleted('business_partner_identifications', ['bp_identification_id' => $ident->bp_identification_id]);

        // Isolation checks
        $roleB = BusinessPartnerRole::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->tenant_id,
            'business_partner_id' => $this->partnerB->business_partner_id,
            'role_type' => 2,
            'role_code' => 'SUPPLIER',
            'status' => 1,
        ]);

        $this->assertNotContains(
            $roleB->business_partner_role_id,
            BusinessPartnerRole::pluck('business_partner_role_id')->toArray()
        );
    }

    #[Test]
    public function soft_deleted_partner_code_can_be_reused_due_to_partial_unique(): void
    {
        $partner = BusinessPartner::withoutGlobalScopes()->create([
            'business_partner_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'code' => 'REUSE-CODE',
            'display_name' => 'To Soft Delete',
            'partner_type' => 1,
            'status' => 1,
        ]);

        $partner->delete();
        $this->assertSoftDeleted('business_partners', ['business_partner_id' => $partner->business_partner_id]);

        // Same code must be creatable again under same tenant (partial unique WHERE deleted_at IS NULL)
        $recreated = BusinessPartner::withoutGlobalScopes()->create([
            'business_partner_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->tenant_id,
            'code' => 'REUSE-CODE',
            'display_name' => 'Recreated',
            'partner_type' => 1,
            'status' => 1,
        ]);

        $this->assertDatabaseHas('business_partners', [
            'business_partner_id' => $recreated->business_partner_id,
            'code' => 'REUSE-CODE',
            'deleted_at' => null,
        ]);
    }
}
