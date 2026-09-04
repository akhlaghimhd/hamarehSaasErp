<?php

namespace Tests\Feature\Modules\Inventory;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\Inventory\Models\InventoryDocument;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-INV-04 — InventoryDocument header CRUD + Tenant Isolation + Draft-only mutation
 */
class InventoryDocumentCrudAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'INV_DOC_A',
            'status'      => 1,
        ]);

        $this->userA = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'user_id'   => $this->userA->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'code'      => 'inv-doc-mgr',
            'name'      => 'Document Manager',
            'status'    => 1,
        ]);

        foreach ([
            'inventory.document.view',
            'inventory.document.create',
            'inventory.document.update',
            'inventory.document.delete',
        ] as $code) {
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenantA->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'Inventory',
                'action_type'          => strtoupper(explode('.', $code)[2] ?? 'VIEW'),
                'status'               => 1,
            ]);
            TenantRolePermission::create([
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id'                 => $this->tenantA->tenant_id,
                'tenant_role_id'            => $role->tenant_role_id,
                'tenant_permission_id'      => $perm->tenant_permission_id,
            ]);
        }

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenantA->tenant_id,
            'user_id'             => $this->userA->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        $this->tokenA = $this->userA->createToken(
            'doc-a',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        $this->tenantB = Tenant::factory()->create([
            'tenant_code' => 'INV_DOC_B',
            'status'      => 1,
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

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->tokenA,
            'X-Tenant-ID'   => $this->tenantA->tenant_id,
            'Accept'        => 'application/json',
        ];
    }

    #[Test]
    public function can_create_list_show_update_and_soft_delete_draft_document(): void
    {
        $createResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/inventory/documents', [
                'fiscal_period_id' => (string) Str::uuid(),
                'document_type'    => 1,
                'document_number'  => 'GR-2026-0001',
                'description'      => 'Goods receipt draft',
                'status'           => 1,
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.document_number', 'GR-2026-0001')
            ->assertJsonPath('data.status', 1);

        $documentId = $createResponse->json('data.document_id');
        $this->assertNotEmpty($documentId);

        $this->assertDatabaseHas('inv_documents', [
            'document_id'     => $documentId,
            'tenant_id'       => $this->tenantA->tenant_id,
            'document_number' => 'GR-2026-0001',
        ]);

        $indexResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/documents?document_type=1');

        $indexResponse->assertStatus(200)->assertJsonPath('success', true);
        $ids = collect($indexResponse->json('data'))->pluck('document_id')->toArray();
        $this->assertContains($documentId, $ids);

        $showResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/documents/' . $documentId);

        $showResponse->assertStatus(200)
            ->assertJsonPath('data.document_id', $documentId);

        $updateResponse = $this->withHeaders($this->authHeaders())
            ->putJson('/api/inventory/documents/' . $documentId, [
                'description' => 'Goods receipt updated',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.description', 'Goods receipt updated');

        $deleteResponse = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/inventory/documents/' . $documentId);

        $deleteResponse->assertStatus(200)->assertJsonPath('success', true);

        $this->assertSoftDeleted('inv_documents', [
            'document_id' => $documentId,
            'tenant_id'   => $this->tenantA->tenant_id,
        ]);
    }

    #[Test]
    public function tenant_a_cannot_see_document_of_tenant_b(): void
    {
        $docB = InventoryDocument::withoutGlobalScopes()->create([
            'document_id'      => (string) Str::uuid(),
            'tenant_id'        => $this->tenantB->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type'    => 2,
            'document_number'  => 'GI-B-ONLY',
            'status'           => 1,
        ]);

        $indexResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/documents');

        $indexResponse->assertStatus(200);
        $ids = collect($indexResponse->json('data'))->pluck('document_id')->toArray();
        $this->assertNotContains($docB->document_id, $ids);

        $showResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/inventory/documents/' . $docB->document_id);

        $showResponse->assertStatus(404);
    }

    #[Test]
    public function non_draft_document_cannot_be_updated_or_deleted(): void
    {
        $doc = InventoryDocument::withoutGlobalScopes()->create([
            'document_id'      => (string) Str::uuid(),
            'tenant_id'        => $this->tenantA->tenant_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'document_type'    => 1,
            'document_number'  => 'GR-POSTED-01',
            'status'           => 3, // Posted
        ]);

        $updateResponse = $this->withHeaders($this->authHeaders())
            ->putJson('/api/inventory/documents/' . $doc->document_id, [
                'description' => 'Should fail',
            ]);

        $updateResponse->assertStatus(409);

        $deleteResponse = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/inventory/documents/' . $doc->document_id);

        $deleteResponse->assertStatus(409);

        $this->assertDatabaseHas('inv_documents', [
            'document_id' => $doc->document_id,
            'deleted_at'  => null,
        ]);
    }
}
