<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Services\RoleAssignmentValidityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RoleAssignmentValidityTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId;

    private string $userId;

    private RoleAssignmentValidityService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (string) Str::uuid();
        $this->userId = (string) Str::uuid();

        DB::table('tenants')->insert([
            'tenant_id'   => $this->tenantId,
            'tenant_code' => 'TBA1',
            'tenant_name' => 'Time Bound Tenant',
            'slug'        => 'tba-tenant',
            'status'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::table('users')->insert([
            'user_id'    => $this->userId,
            'first_name' => 'T',
            'last_name'  => 'B',
            'email'      => 'tba@example.com',
            'mobile'     => '09121112233',
            'user_kind'  => 1,
            'status'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'row_version'=> 1,
        ]);

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantId]);
        app()->instance('current_tenant_id', $this->tenantId);

        $this->svc = app(RoleAssignmentValidityService::class);
    }

    private function makeRole(string $code): string
    {
        $role = TenantRole::create([
            'tenant_id' => $this->tenantId,
            'code'      => $code,
            'name'      => $code,
            'status'    => 1,
        ]);

        return (string) $role->tenant_role_id;
    }

    private function assign(string $roleId, $from = null, $to = null): void
    {
        DB::table('tenant_user_roles')->insert([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenantId,
            'user_id'             => $this->userId,
            'tenant_role_id'      => $roleId,
            'valid_from'          => $from,
            'valid_to'            => $to,
            'created_at'          => now(),
            'updated_at'          => now(),
            'row_version'         => 1,
        ]);
    }

    #[Test]
    public function open_ended_assignment_is_effective(): void
    {
        $roleId = $this->makeRole('open');
        $this->assign($roleId);

        $ids = $this->svc->effectiveRoleIds($this->tenantId, $this->userId);
        $this->assertContains($roleId, $ids);
    }

    #[Test]
    public function future_valid_from_not_yet_effective(): void
    {
        $roleId = $this->makeRole('future');
        $this->assign($roleId, now()->addDay(), now()->addDays(10));

        $ids = $this->svc->effectiveRoleIds($this->tenantId, $this->userId);
        $this->assertNotContains($roleId, $ids);
    }

    #[Test]
    public function expired_valid_to_not_effective(): void
    {
        $roleId = $this->makeRole('expired');
        $this->assign($roleId, now()->subDays(10), now()->subDay());

        $ids = $this->svc->effectiveRoleIds($this->tenantId, $this->userId);
        $this->assertNotContains($roleId, $ids);
    }

    #[Test]
    public function currently_in_window_is_effective(): void
    {
        $roleId = $this->makeRole('active-window');
        $this->assign($roleId, now()->subDay(), now()->addDay());

        $ids = $this->svc->effectiveRoleIds($this->tenantId, $this->userId);
        $this->assertContains($roleId, $ids);
    }

    #[Test]
    public function expire_stale_soft_deletes_past_assignments(): void
    {
        $roleId = $this->makeRole('to-expire');
        $this->assign($roleId, now()->subDays(5), now()->subHour());

        $n = $this->svc->expireStale($this->tenantId);
        $this->assertGreaterThanOrEqual(1, $n);

        $this->assertTrue(
            DB::table('tenant_user_roles')
                ->where('tenant_role_id', $roleId)
                ->whereNotNull('deleted_at')
                ->exists()
        );
    }

    #[Test]
    public function invalid_window_rejected(): void
    {
        $this->expectException(HttpException::class);
        $this->svc->assertValidWindow(
            now()->addDay()->toIso8601String(),
            now()->toIso8601String()
        );
    }
}
