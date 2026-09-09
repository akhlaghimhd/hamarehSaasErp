<?php

namespace Tests\Feature\Modules\PartnerLayer;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\PartnerLayer\Models\Partner;
use App\Modules\PartnerLayer\Models\PartnerDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L3-P3-03 — PartnerDocument CRUD feature tests.
 */
class PartnerDocumentCrudTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $token;
    protected Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'P3_DOC', 'status' => 1]);
        $this->user = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->user->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'DOC_MGR',
            'name'      => 'Document Manager',
        ]);

        foreach (['partner.document.view', 'partner.document.create', 'partner.document.update', 'partner.document.delete'] as $code) {
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenant->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'PartnerLayer',
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
            'doc-test',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $this->partner = Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'DOC-P',
            'name'       => 'Partner Document Owner',
            'status'     => 1,
        ]);
    }

    #[Test]
    public function it_creates_and_lists_partner_documents(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
        ])->postJson('/api/partner-layer/partner-documents', [
            'partner_id'      => $this->partner->partner_id,
            'document_type'   => 'NDA',
            'document_number' => 'DOC-001',
            'storage_path'    => '/secure/partners/nda-001.pdf',
            'status'          => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('partner_documents', [
            'partner_id'    => $this->partner->partner_id,
            'document_type' => 'NDA',
            'storage_path'  => '/secure/partners/nda-001.pdf',
        ]);

        $list = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
        ])->getJson('/api/partner-layer/partner-documents?partner_id=' . $this->partner->partner_id);

        $list->assertStatus(200)->assertJsonPath('status', 'success');
    }

    #[Test]
    public function it_updates_and_deletes_partner_document(): void
    {
        $doc = PartnerDocument::create([
            'partner_document_id' => (string) Str::uuid(),
            'partner_id'          => $this->partner->partner_id,
            'document_type'       => 'Contract',
            'storage_path'        => '/secure/c1.pdf',
            'status'              => 1,
        ]);

        $update = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
        ])->putJson('/api/partner-layer/partner-documents/' . $doc->partner_document_id, [
            'document_type' => 'Contract',
            'storage_path'  => '/secure/c1-v2.pdf',
            'status'        => 2,
        ]);

        $update->assertStatus(200)->assertJsonPath('status', 'success');

        $delete = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
        ])->deleteJson('/api/partner-layer/partner-documents/' . $doc->partner_document_id);

        $delete->assertStatus(200)->assertJsonPath('status', 'success');
    }
}
