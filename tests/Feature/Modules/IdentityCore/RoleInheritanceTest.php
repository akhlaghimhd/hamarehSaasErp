<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Services\RoleInheritanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RoleInheritanceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId;

    private RoleInheritanceService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (string) Str::uuid();
        DB::table('tenants')->insert([
            'tenant_id'   => $this->tenantId,
            'tenant_code' => 'INH1',
            'tenant_name' => 'Inherit Tenant',
            'slug'        => 'inherit-tenant',
            'status'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantId]);

        $this->svc = app(RoleInheritanceService::class);
    }

    #[Test]
    public function child_role_inherits_parent_permissions(): void
    {
        $parent = TenantRole::create([
            'tenant_role_id' => (string) Str::uuid(),
            'tenant_id'      => $this->tenantId,
            'code'           => 'parent-role',
            'name'           => 'Parent',
            'status'         => 1,
        ]);

        $child = TenantRole::create([
            'tenant_role_id' => (string) Str::uuid(),
            'tenant_id'      => $this->tenantId,
            'parent_role_id' => $parent->tenant_role_id,
            'code'           => 'child-role',
            'name'           => 'Child',
            'status'         => 1,
        ]);

        $permParent = TenantPermission::create([
            'tenant_permission_id' => (string) Str::uuid(),
            'tenant_id'            => $this->tenantId,
            'code'                 => 'identity.user.view',
            'name'                 => 'View users',
            'module_name'          => 'identity',
            'action_type'          => 'READ',
            'status'               => 1,
        ]);

        $permChild = TenantPermission::create([
            'tenant_permission_id' => (string) Str::uuid(),
            'tenant_id'            => $this->tenantId,
            'code'                 => 'identity.role.view',
            'name'                 => 'View roles',
            'module_name'          => 'identity',
            'action_type'          => 'READ',
            'status'               => 1,
        ]);

        TenantRolePermission::create([
            'tenant_role_permission_id' => (string) Str::uuid(),
            'tenant_id'                 => $this->tenantId,
            'tenant_role_id'            => $parent->tenant_role_id,
            'tenant_permission_id'      => $permParent->tenant_permission_id,
        ]);

        TenantRolePermission::create([
            'tenant_role_permission_id' => (string) Str::uuid(),
            'tenant_id'                 => $this->tenantId,
            'tenant_role_id'            => $child->tenant_role_id,
            'tenant_permission_id'      => $permChild->tenant_permission_id,
        ]);

        $codes = $this->svc->permissionCodesForRoles($this->tenantId, [$child->tenant_role_id]);

        $this->assertContains('identity.user.view', $codes);
        $this->assertContains('identity.role.view', $codes);
    }

    #[Test]
    public function expand_includes_ancestor_chain(): void
    {
        $grand = TenantRole::create([
            'tenant_role_id' => (string) Str::uuid(),
            'tenant_id'      => $this->tenantId,
            'code'           => 'grand',
            'name'           => 'Grand',
            'status'         => 1,
        ]);

        $parent = TenantRole::create([
            'tenant_role_id' => (string) Str::uuid(),
            'tenant_id'      => $this->tenantId,
            'parent_role_id' => $grand->tenant_role_id,
            'code'           => 'parent',
            'name'           => 'Parent',
            'status'         => 1,
        ]);

        $child = TenantRole::create([
            'tenant_role_id' => (string) Str::uuid(),
            'tenant_id'      => $this->tenantId,
            'parent_role_id' => $parent->tenant_role_id,
            'code'           => 'child',
            'name'           => 'Child',
            'status'         => 1,
        ]);

        $expanded = $this->svc->expandWithAncestors($this->tenantId, [$child->tenant_role_id]);

        $this->assertContains($child->tenant_role_id, $expanded);
        $this->assertContains($parent->tenant_role_id, $expanded);
        $this->assertContains($grand->tenant_role_id, $expanded);
        $this->assertCount(3, $expanded);
    }

    #[Test]
    public function cycle_detection_rejects_loop(): void
    {
        $a = TenantRole::create([
            'tenant_role_id' => (string) Str::uuid(),
            'tenant_id'      => $this->tenantId,
            'code'           => 'role-a',
            'name'           => 'A',
            'status'         => 1,
        ]);

        $b = TenantRole::create([
            'tenant_role_id' => (string) Str::uuid(),
            'tenant_id'      => $this->tenantId,
            'parent_role_id' => $a->tenant_role_id,
            'code'           => 'role-b',
            'name'           => 'B',
            'status'         => 1,
        ]);

        // A → B is fine; setting A.parent = B would cycle
        $this->expectException(HttpException::class);
        $this->svc->assertNoCycle($this->tenantId, $a->tenant_role_id, $b->tenant_role_id);
    }

    #[Test]
    public function self_parent_rejected(): void
    {
        $role = TenantRole::create([
            'tenant_role_id' => (string) Str::uuid(),
            'tenant_id'      => $this->tenantId,
            'code'           => 'solo',
            'name'           => 'Solo',
            'status'         => 1,
        ]);

        $this->expectException(HttpException::class);
        $this->svc->assertNoCycle($this->tenantId, $role->tenant_role_id, $role->tenant_role_id);
    }
}
