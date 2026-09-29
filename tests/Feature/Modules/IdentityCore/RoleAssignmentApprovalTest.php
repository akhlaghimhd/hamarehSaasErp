<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Services\RoleAssignmentApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RoleAssignmentApprovalTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId;

    private string $subjectUserId;

    private string $requesterId;

    private string $approverId;

    private string $roleId;

    private RoleAssignmentApprovalService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (string) Str::uuid();
        $this->subjectUserId = (string) Str::uuid();
        $this->requesterId = (string) Str::uuid();
        $this->approverId = (string) Str::uuid();

        DB::table('tenants')->insert([
            'tenant_id'   => $this->tenantId,
            'tenant_code' => 'RAP1',
            'tenant_name' => 'Role Approval Tenant',
            'slug'        => 'rap-tenant',
            'status'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        foreach ([
            [$this->subjectUserId, 'sub@example.com', '09120000001'],
            [$this->requesterId, 'req@example.com', '09120000002'],
            [$this->approverId, 'apr@example.com', '09120000003'],
        ] as [$uid, $email, $mobile]) {
            DB::table('users')->insert([
                'user_id'     => $uid,
                'first_name'  => 'U',
                'last_name'   => 'T',
                'email'       => $email,
                'mobile'      => $mobile,
                'user_kind'   => 1,
                'status'      => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
                'row_version' => 1,
            ]);
        }

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantId]);
        app()->instance('current_tenant_id', $this->tenantId);

        $role = TenantRole::create([
            'tenant_id'     => $this->tenantId,
            'code'          => 'finance-admin',
            'name'          => 'Finance Admin',
            'status'        => 1,
            'is_privileged' => true,
        ]);
        $this->roleId = (string) $role->tenant_role_id;

        $this->svc = app(RoleAssignmentApprovalService::class);
    }

    #[Test]
    public function request_creates_pending(): void
    {
        $req = $this->svc->requestAssignment(
            $this->tenantId,
            $this->subjectUserId,
            $this->roleId,
            $this->requesterId,
            'Need quarter close access'
        );

        $this->assertSame('PENDING', $req->status);
        $this->assertSame($this->subjectUserId, $req->user_id);
    }

    #[Test]
    public function approve_creates_user_role(): void
    {
        $req = $this->svc->requestAssignment(
            $this->tenantId,
            $this->subjectUserId,
            $this->roleId,
            $this->requesterId
        );

        $approved = $this->svc->approve(
            $this->tenantId,
            $req->request_id,
            $this->approverId,
            'OK for Q3'
        );

        $this->assertSame('APPROVED', $approved->status);

        $this->assertTrue(
            DB::table('tenant_user_roles')
                ->where('tenant_id', $this->tenantId)
                ->where('user_id', $this->subjectUserId)
                ->where('tenant_role_id', $this->roleId)
                ->whereNull('deleted_at')
                ->exists()
        );
    }

    #[Test]
    public function self_approve_rejected(): void
    {
        $req = $this->svc->requestAssignment(
            $this->tenantId,
            $this->subjectUserId,
            $this->roleId,
            $this->requesterId
        );

        $this->expectException(HttpException::class);
        $this->svc->approve($this->tenantId, $req->request_id, $this->requesterId);
    }

    #[Test]
    public function reject_leaves_no_role(): void
    {
        $req = $this->svc->requestAssignment(
            $this->tenantId,
            $this->subjectUserId,
            $this->roleId,
            $this->requesterId
        );

        $rejected = $this->svc->reject(
            $this->tenantId,
            $req->request_id,
            $this->approverId,
            'Not needed'
        );

        $this->assertSame('REJECTED', $rejected->status);

        $this->assertFalse(
            DB::table('tenant_user_roles')
                ->where('user_id', $this->subjectUserId)
                ->where('tenant_role_id', $this->roleId)
                ->whereNull('deleted_at')
                ->exists()
        );
    }

    #[Test]
    public function duplicate_pending_rejected(): void
    {
        $this->svc->requestAssignment(
            $this->tenantId,
            $this->subjectUserId,
            $this->roleId,
            $this->requesterId
        );

        $this->expectException(HttpException::class);
        $this->svc->requestAssignment(
            $this->tenantId,
            $this->subjectUserId,
            $this->roleId,
            $this->requesterId
        );
    }
}
