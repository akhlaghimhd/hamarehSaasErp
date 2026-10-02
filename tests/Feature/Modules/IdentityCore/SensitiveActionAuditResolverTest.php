<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Base\Support\SensitiveActionAuditContext;
use App\Base\Support\SensitiveActionAuditResolver;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Services\PrivilegedAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SensitiveActionAuditResolverTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId;

    private User $user;

    private User $approver;

    private TenantRole $privRole;

    private PrivilegedAccessService $privSvc;

    private SensitiveActionAuditResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (string) Str::uuid();
        DB::table('tenants')->insert([
            'tenant_id'   => $this->tenantId,
            'tenant_code' => 'AUD1',
            'tenant_name' => 'Audit Tenant',
            'slug'        => 'audit-tenant',
            'status'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantId]);

        $this->user = User::factory()->create([
            'email'  => 'audit.user@example.com',
            'mobile' => '09121112233',
            'status' => 1,
        ]);

        $this->approver = User::factory()->create([
            'email'  => 'audit.approver@example.com',
            'mobile' => '09124445566',
            'status' => 1,
        ]);

        TenantUser::create([
            'tenant_user_id' => (string) Str::uuid(),
            'tenant_id'      => $this->tenantId,
            'user_id'        => $this->user->user_id,
            'status'         => 1,
            'is_owner'       => false,
        ]);

        TenantUser::create([
            'tenant_user_id' => (string) Str::uuid(),
            'tenant_id'      => $this->tenantId,
            'user_id'        => $this->approver->user_id,
            'status'         => 1,
            'is_owner'       => true,
        ]);

        $this->privRole = TenantRole::create([
            'tenant_role_id' => (string) Str::uuid(),
            'tenant_id'      => $this->tenantId,
            'code'           => 'break-glass-audit',
            'name'           => 'Break Glass Audit',
            'status'         => 1,
            'is_privileged'  => true,
        ]);

        $this->privSvc = app(PrivilegedAccessService::class);
        $this->resolver = app(SensitiveActionAuditResolver::class);
    }

    #[Test]
    public function standing_when_no_active_grant(): void
    {
        $ctx = $this->resolver->resolve(
            $this->tenantId,
            $this->user->user_id,
            'VOUCHER_POST',
            'accounting_voucher',
            (string) Str::uuid(),
            'accounting.voucher.post'
        );

        $this->assertSame(SensitiveActionAuditContext::CHANNEL_STANDING, $ctx->accessChannel);
        $this->assertNull($ctx->privilegedGrantId);
        $payload = $ctx->toArray();
        $this->assertArrayHasKey('acted_at', $payload);
        $this->assertSame('VOUCHER_POST', $payload['action_code']);
    }

    #[Test]
    public function privileged_channel_when_active_grant(): void
    {
        $grant = $this->privSvc->requestGrant(
            $this->tenantId,
            $this->user->user_id,
            $this->privRole->tenant_role_id,
            'Need elevated post for incident',
            30,
            $this->user->user_id
        );
        $this->privSvc->approveAndActivate($this->tenantId, $grant->grant_id, $this->approver->user_id);

        $ctx = $this->resolver->resolve(
            $this->tenantId,
            $this->user->user_id,
            'VOUCHER_POST',
            'accounting_voucher',
            (string) Str::uuid(),
            'accounting.voucher.post'
        );

        $this->assertSame(SensitiveActionAuditContext::CHANNEL_PRIVILEGED_GRANT, $ctx->accessChannel);
        $this->assertSame($grant->grant_id, $ctx->privilegedGrantId);
    }

    #[Test]
    public function owner_channel_for_owner_without_grant(): void
    {
        $ctx = $this->resolver->resolve(
            $this->tenantId,
            $this->approver->user_id,
            'ROLE_UPDATE',
            'tenant_role',
            (string) Str::uuid()
        );

        $this->assertSame(SensitiveActionAuditContext::CHANNEL_OWNER, $ctx->accessChannel);
        $this->assertNull($ctx->privilegedGrantId);
    }
}
