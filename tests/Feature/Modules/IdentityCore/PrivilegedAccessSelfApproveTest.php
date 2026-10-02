<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Services\PrivilegedAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PrivilegedAccessSelfApproveTest extends TestCase
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
            'tenant_code' => 'PRIV2',
            'tenant_name' => 'Priv Tenant 2',
            'slug'        => 'priv-tenant-2',
            'status'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantId]);

        $this->user = User::factory()->create([
            'email'  => 'priv2.user@example.com',
            'mobile' => '09120001123',
            'status' => 1,
        ]);

        $this->approver = User::factory()->create([
            'email'  => 'priv2.approver@example.com',
            'mobile' => '09120003345',
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
            'code'           => 'break-glass-admin-2',
            'name'           => 'Break Glass Admin 2',
            'status'         => 1,
            'is_privileged'  => true,
        ]);

        $this->svc = app(PrivilegedAccessService::class);
    }

    #[Test]
    public function cannot_self_approve_as_requester(): void
    {
        $grant = $this->svc->requestGrant(
            $this->tenantId,
            $this->user->user_id,
            $this->privRole->tenant_role_id,
            'Self approve must be blocked by policy',
            30,
            $this->user->user_id
        );

        $this->expectException(HttpException::class);

        $this->svc->approveAndActivate(
            $this->tenantId,
            $grant->grant_id,
            $this->user->user_id
        );
    }

    #[Test]
    public function cannot_self_approve_as_beneficiary_when_requested_by_other(): void
    {
        $grant = $this->svc->requestGrant(
            $this->tenantId,
            $this->user->user_id,
            $this->privRole->tenant_role_id,
            'Beneficiary must not approve own grant',
            30,
            $this->approver->user_id
        );

        $this->expectException(HttpException::class);

        $this->svc->approveAndActivate(
            $this->tenantId,
            $grant->grant_id,
            $this->user->user_id
        );
    }

    #[Test]
    public function active_grants_for_user_at_returns_current_window(): void
    {
        $grant = $this->svc->requestGrant(
            $this->tenantId,
            $this->user->user_id,
            $this->privRole->tenant_role_id,
            'Window check for audit correlation',
            60,
            $this->user->user_id
        );

        $this->svc->approveAndActivate($this->tenantId, $grant->grant_id, $this->approver->user_id);

        $active = $this->svc->activeGrantsForUserAt($this->tenantId, $this->user->user_id, now());
        $this->assertCount(1, $active);
        $this->assertSame($grant->grant_id, $active[0]->grant_id);

        $past = $this->svc->activeGrantsForUserAt($this->tenantId, $this->user->user_id, now()->subDay());
        $this->assertCount(0, $past);
    }
}
