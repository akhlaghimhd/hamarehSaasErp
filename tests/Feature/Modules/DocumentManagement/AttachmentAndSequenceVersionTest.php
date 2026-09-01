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
use App\Modules\DocumentManagement\Models\Attachment;
use App\Modules\DocumentManagement\Models\DocumentSequence;
use App\Modules\DocumentManagement\Models\DocumentVersion;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L5-DM-04 Attachment create/delete + isolation
 * L5-DM-05 Sequence + Version create
 */
class AttachmentAndSequenceVersionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected string $tokenA;
    protected string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create([
            'tenant_code' => 'DOC_ATT_A',
            'status'      => 1,
        ]);
        $this->tenantB = Tenant::factory()->create([
            'tenant_code' => 'DOC_ATT_B',
            'status'      => 1,
        ]);

        $this->userA = User::factory()->create(['status' => 1]);
        $this->userB = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'user_id'   => $this->userA->user_id,
            'status'    => 1,
        ]);
        TenantUser::factory()->create([
            'tenant_id' => $this->tenantB->tenant_id,
            'user_id'   => $this->userB->user_id,
            'status'    => 1,
        ]);

        $roleA = TenantRole::factory()->create([
            'tenant_id' => $this->tenantA->tenant_id,
            'code'      => 'doc-att-full',
            'name'      => 'Document Attachment Full',
            'status'    => 1,
        ]);
        $roleB = TenantRole::factory()->create([
            'tenant_id' => $this->tenantB->tenant_id,
            'code'      => 'doc-att-full',
            'name'      => 'Document Attachment Full',
            'status'    => 1,
        ]);

        $permissionCodes = [
            'document-management.document.view',
            'document-management.document.create',
            'document-management.document.update',
            'document-management.document.delete',
            'document-management.attachment.create',
            'document-management.attachment.delete',
            'document-management.sequence.create',
            'document-management.version.create',
        ];

        foreach ([$this->tenantA, $this->tenantB] as $tenant) {
            $role = $tenant->tenant_id === $this->tenantA->tenant_id ? $roleA : $roleB;
            $user = $tenant->tenant_id === $this->tenantA->tenant_id ? $this->userA : $this->userB;

            foreach ($permissionCodes as $code) {
                $perm = TenantPermission::create([
                    'tenant_permission_id' => (string) Str::uuid(),
                    'tenant_id'            => $tenant->tenant_id,
                    'code'                 => $code,
                    'name'                 => $code,
                    'module_name'          => 'DocumentManagement',
                    'action_type'          => strtoupper(explode('.', $code)[2] ?? 'VIEW'),
                    'status'               => 1,
                ]);
                TenantRolePermission::create([
                    'tenant_role_permission_id' => (string) Str::uuid(),
                    'tenant_id'                 => $tenant->tenant_id,
                    'tenant_role_id'            => $role->tenant_role_id,
                    'tenant_permission_id'      => $perm->tenant_permission_id,
                ]);
            }

            TenantUserRole::create([
                'tenant_user_role_id' => (string) Str::uuid(),
                'tenant_id'           => $tenant->tenant_id,
                'user_id'             => $user->user_id,
                'tenant_role_id'      => $role->tenant_role_id,
            ]);
        }

        $this->tokenA = $this->userA->createToken(
            'doc-att-a',
            ['tenant:' . $this->tenantA->tenant_id]
        )->plainTextToken;
        $this->tokenB = $this->userB->createToken(
            'doc-att-b',
            ['tenant:' . $this->tenantB->tenant_id]
        )->plainTextToken;

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
    public function can_create_and_soft_delete_attachment(): void
    {
        $entityId = (string) Str::uuid();

        $createResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/document-management/attachments', [
                'target_entity_type' => 'document',
                'target_entity_id'   => $entityId,
                'file_name'          => 'invoice.pdf',
                'file_path'          => '/storage/docs/invoice.pdf',
                'mime_type'          => 'application/pdf',
                'file_size_bytes'    => 10240,
                'file_hash'          => hash('sha256', 'test-content'),
            ]);

        $createResponse->assertStatus(201);
        $attachmentId = $createResponse->json('data.attachment_id');
        $this->assertNotEmpty($attachmentId);

        $this->assertDatabaseHas('attachments', [
            'attachment_id'      => $attachmentId,
            'tenant_id'          => $this->tenantA->tenant_id,
            'target_entity_type' => 'document',
            'file_name'          => 'invoice.pdf',
        ]);

        $deleteResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->deleteJson('/api/document-management/attachments/' . $attachmentId);

        $deleteResponse->assertStatus(200);
        $this->assertSoftDeleted('attachments', ['attachment_id' => $attachmentId]);
    }

    #[Test]
    public function tenant_isolation_on_attachment_delete(): void
    {
        $attachmentB = Attachment::withoutGlobalScopes()->create([
            'attachment_id'      => (string) Str::uuid(),
            'tenant_id'          => $this->tenantB->tenant_id,
            'target_entity_type' => 'document',
            'target_entity_id'   => (string) Str::uuid(),
            'file_name'          => 'secret.pdf',
            'file_path'          => '/storage/b/secret.pdf',
            'mime_type'          => 'application/pdf',
            'file_size_bytes'    => 100,
        ]);

        $deleteAsA = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->deleteJson('/api/document-management/attachments/' . $attachmentB->attachment_id);

        $this->assertTrue(
            in_array($deleteAsA->status(), [400, 403, 404]),
            'Expected isolation deny on cross-tenant delete, got ' . $deleteAsA->status()
        );

        $this->assertDatabaseHas('attachments', [
            'attachment_id' => $attachmentB->attachment_id,
            'deleted_at'    => null,
        ]);
    }

    #[Test]
    public function can_create_document_sequence(): void
    {
        $createResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/document-management/sequences', [
                'module_code'     => 'ACC',
                'document_type'   => 'INVOICE',
                'document_scope'  => 1,
                'company_id'      => null,
                'prefix'          => 'INV-',
                'suffix'          => null,
                'padding_length'  => 6,
                'reset_period'    => 1,
            ]);

        $createResponse->assertStatus(201);
        $sequenceId = $createResponse->json('data.sequence_id');
        $this->assertNotEmpty($sequenceId);

        $this->assertDatabaseHas('document_sequences', [
            'sequence_id'   => $sequenceId,
            'tenant_id'     => $this->tenantA->tenant_id,
            'module_code'   => 'ACC',
            'document_type' => 'INVOICE',
            'prefix'        => 'INV-',
        ]);
    }

    #[Test]
    public function can_create_document_version_for_existing_document(): void
    {
        $document = Document::withoutGlobalScopes()->create([
            'document_id'     => (string) Str::uuid(),
            'tenant_id'       => $this->tenantA->tenant_id,
            'document_number' => 'DOC-VER-001',
            'title'           => 'Versioned Doc',
            'document_type'   => 'CONTRACT',
            'status'          => 1,
        ]);

        $createResponse = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/document-management/versions', [
                'document_id'    => $document->document_id,
                'version_number' => 1,
                'change_summary' => 'Initial version',
            ]);

        $createResponse->assertStatus(201);
        $versionId = $createResponse->json('data.version_id');
        $this->assertNotEmpty($versionId);

        $this->assertDatabaseHas('document_versions', [
            'version_id'     => $versionId,
            'tenant_id'      => $this->tenantA->tenant_id,
            'document_id'    => $document->document_id,
            'version_number' => 1,
            'change_summary' => 'Initial version',
        ]);
    }

    #[Test]
    public function duplicate_sequence_for_same_scope_is_rejected(): void
    {
        $payload = [
            'module_code'    => 'ACC',
            'document_type'  => 'CREDIT_NOTE',
            'document_scope' => 1,
            'prefix'         => 'CN-',
            'padding_length' => 5,
            'reset_period'   => 1,
        ];

        $first = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/document-management/sequences', $payload);
        $first->assertStatus(201);

        $second = $this->withHeaders($this->authHeaders($this->tokenA, $this->tenantA->tenant_id))
            ->postJson('/api/document-management/sequences', $payload);

        $this->assertTrue(
            in_array($second->status(), [400, 422]),
            'Expected duplicate sequence rejection, got ' . $second->status()
        );
    }
}
