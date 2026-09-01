<?php

namespace Tests\Feature\Modules\DocumentManagement;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\DocumentManagement\Models\Document;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * Layer 5 – DocumentManagement CRUD + Tenant Isolation + SoftDelete
 * Covers L5-DM-01 (show) + L5-DM-03
 */
class DocumentCrudAndIsolationTest extends TestCase
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
            'tenant_code' => 'DOC_CRUD_A',
            'status'      => 1,
        ]);

        $this->userA = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'user_id'   => $this->userA->user_id,
            'status'    => 1,
        ]);

        $roleA = TenantRole::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'code'      => 'doc-full',
            'name'      => 'Document Full Access',
            'status'    => 1,
        ]);

        $permissionCodes = [
            'document-management.document.view',
            'document-management.document.create',
            'document-management.document.update',
            'document-management.document.delete',
        ];

        foreach ($permissionCodes as $code) {
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenantA->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'DocumentManagement',
                'action_type'          => strtoupper(explode('.', $code)[2] ?? 'VIEW'),
                'status'               => 1,
            ]);

            TenantRolePermission::create([
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id'                 => $this->tenantA->tenant_id,
                'tenant_role_id'            => $roleA->tenant_role_id,
                'tenant_permission_id'      => $perm->tenant_permission_id,
            ]);
        }

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenantA->tenant_id,
            'user_id'             => $this->userA->user_id,
            'tenant_role_id'      => $roleA->tenant_role_id,
        ]);

        $this->tokenA = $this->userA->createToken(
            'doc-crud-a',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;

        $this->tenantB = Tenant::factory()->create([
            'tenant_code' => 'DOC_CRUD_B',
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

    protected function authHeaders(string $token, string $tenantId): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID'   => $tenantId,
            'Accept'        => 'application/json',
        ];
    }

    #[Test]
    public function can_create_list_show_update_and_soft_delete_document(): void
    {
        $createPayload = [
            'document_number' => 'DOC-001',
            'title'           => 'Test Document',
            'document_type'   => 'INVOICE',
            'description'     => 'Test description',
            'status'          => 1,
        ];

        $createResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/document-management/documents', $createPayload);

        $createResponse->assertStatus(201);

        $documentId = $createResponse->json('data.document_id');
        $this->assertNotEmpty($documentId);

        $this->assertDatabaseHas('documents', [
            'document_id'     => $documentId,
            'tenant_id'       => $this->tenantA->tenant_id,
            'document_number' => 'DOC-001',
        ]);

        // show
        $showResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/document-management/documents/' . $documentId);

        $showResponse->assertStatus(200)
            ->assertJsonPath('data.document_id', $documentId)
            ->assertJsonPath('data.document_number', 'DOC-001');

        // index
        $indexResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/document-management/documents');

        $indexResponse->assertStatus(200);
        $ids = collect($indexResponse->json('data'))->pluck('document_id')->toArray();
        $this->assertContains($documentId, $ids);

        // update
        $updateResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->putJson('/api/document-management/documents/' . $documentId, [
                'document_number' => 'DOC-001-UPD',
                'title'           => 'Updated Title',
                'document_type'   => 'INVOICE',
                'status'          => 1,
            ]);

        $updateResponse->assertStatus(200);

        // soft delete
        $deleteResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->deleteJson('/api/document-management/documents/' . $documentId);

        $deleteResponse->assertStatus(200);

        $this->assertSoftDeleted('documents', [
            'document_id' => $documentId,
            'tenant_id'   => $this->tenantA->tenant_id,
        ]);
    }

    #[Test]
    public function tenant_a_cannot_see_document_of_tenant_b(): void
    {
        $documentB = Document::withoutGlobalScopes()->create([
            'document_id'     => (string) Str::uuid(),
            'tenant_id'       => $this->tenantB->tenant_id,
            'document_number' => 'DOC-B-ONLY',
            'title'           => 'Tenant B Document',
            'document_type'   => 'INVOICE',
            'status'          => 1,
        ]);

        $indexResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/document-management/documents');

        $indexResponse->assertStatus(200);
        $ids = collect($indexResponse->json('data'))->pluck('document_id')->toArray();
        $this->assertNotContains($documentB->document_id, $ids);

        $showResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->getJson('/api/document-management/documents/' . $documentB->document_id);

        $showResponse->assertStatus(404);
    }
}
