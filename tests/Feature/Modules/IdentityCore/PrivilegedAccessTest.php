<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Modules\IdentityCore\Models\TenantPrivilegedGrant;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Services\PrivilegedAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PrivilegedAccessTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId;

    private User $user;

    private User $approver;

    private TenantRole $privRole;

    private PrivilegedAccessService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (string) Str::uuid();
        DB::table('tenants')->insert([
            'tenant_id'   => $this->tenantId,
            'tenant_code' => 'PRIV1',
            'tenant_name' => 'Priv Tenant',
            'slug'        => 'priv-tenant',
            'status'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantId]);

        $this->user = User::factory()->create([
            'email'  => 'priv.user@example.com',
            'mobile' => '09120001122',
            'status' => 1,
        ]);

        $this->approver = User::factory()->create([
            'email'  => 'priv.approver@example.com',
            'mobile' => '09120003344',
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
            'code'           => 'break-glass-admin',
            'name'           => 'Break Glass Admin',
            'status'         => 1,
            'is_privileged'  => true,
        ]);

        $this->svc = app(PrivilegedAccessService::class);
    }

    #[Test]
    public function request_approve_materializes_role_assignment(): void
    {
        $grant = $this->svc->requestGrant(
            $this->tenantId,
            $this->user->user_id,
            $this->privRole->tenant_role_id,
            'Production incident #42 needs elevated access',
            30,
            $this->user->user_id
        );

        $this->assertSame(TenantPrivilegedGrant::STATUS_PENDING, $grant->status);

        $active = $this->svc->approveAndActivate(
            $this->tenantId,
            $grant->grant_id,
            $this->approver->user_id
        );

        $this->assertSame(TenantPrivilegedGrant::STATUS_ACTIVE, $active->status);
        $this->assertNotNull($active->ends_at);

        $this->assertTrue(
            TenantUserRole::query()
                ->where('tenant_id', $this->tenantId)
                ->where('user_id', $this->user->user_id)
                ->where('tenant_role_id', $this->privRole->tenant_role_id)
                ->exists()
        );
    }

    #[Test]
    public function revoke_removes_role_assignment(): void
    {
        $grant = $this->svc->requestGrant(
            $this->tenantId,
            $this->user->user_id,
            $this->privRole->tenant_role_id,
            'Need elevated access for migration',
            60,
            $this->user->user_id
        );

        $this->svc->approveAndActivate($this->tenantId, $grant->grant_id, $this->approver->user_id);

        $revoked = $this->svc->revoke(
            $this->tenantId,
            $grant->grant_id,
            $this->approver->user_id,
            'Incident resolved'
        );

        $this->assertSame(TenantPrivilegedGrant::STATUS_REVOKED, $revoked->status);

        $this->assertFalse(
            TenantUserRole::query()
                ->where('tenant_id', $this->tenantId)
                ->where('user_id', $this->user->user_id)
                ->where('tenant_role_id', $this->privRole->tenant_role_id)
                ->exists()
        );
    }

    #[Test]
    public function cannot_request_non_privileged_role(): void
    {
        $normal = TenantRole::create([
            'tenant_role_id' => (string) Str::uuid(),
            'tenant_id'      => $this->tenantId,
            'code'           => 'viewer',
            'name'           => 'Viewer',
            'status'         => 1,
            'is_privileged'  => false,
        ]);

        $this->expectException(HttpException::class);

        $this->svc->requestGrant(
            $this->tenantId,
            $this->user->user_id,
            $normal->tenant_role_id,
            'Should fail because role is not privileged',
            30
        );
    }

    #[Test]
    public function expire_stale_clears_assignment(): void
    {
        $grant = $this->svc->requestGrant(
            $this->tenantId,
            $this->user->user_id,
            $this->privRole->tenant_role_id,
            'Short lived grant for expire test',
            5,
            $this->user->user_id
        );

        $active = $this->svc->approveAndActivate($this->tenantId, $grant->grant_id, $this->approver->user_id);

        // Force ends_at into the past
        $active->ends_at = now()->subMinute();
        $active->save();

        $n = $this->svc->expireStale($this->tenantId);
        $this->assertSame(1, $n);

        $fresh = TenantPrivilegedGrant::query()->where('grant_id', $grant->grant_id)->first();
        $this->assertSame(TenantPrivilegedGrant::STATUS_EXPIRED, $fresh->status);

        $this->assertFalse(
            TenantUserRole::query()
                ->where('tenant_id', $this->tenantId)
                ->where('user_id', $this->user->user_id)
                ->where('tenant_role_id', $this->privRole->tenant_role_id)
                ->exists()
        );
    }
}
