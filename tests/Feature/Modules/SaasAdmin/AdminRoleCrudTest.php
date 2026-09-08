<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Modules\SaasAdmin\Models\AdminPermission;
use App\Modules\SaasAdmin\Models\AdminRole;
use App\Modules\SaasAdmin\Services\AdminRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminRoleCrudTest extends TestCase
{
    use RefreshDatabase;

    private AdminRoleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AdminRoleService::class);
    }

    #[Test]
    public function it_creates_admin_role(): void
    {
        $role = $this->service->create(
            code: 'platform-admin',
            name: 'Platform Administrator',
            description: 'Full platform access'
        );

        $this->assertNotNull($role->admin_role_id);
        $this->assertEquals('platform-admin', $role->code);
        $this->assertEquals(1, $role->status);
        $this->assertDatabaseHas('admin_roles', [
            'code' => 'platform-admin',
            'name' => 'Platform Administrator',
        ]);
    }

    #[Test]
    public function it_rejects_duplicate_role_code(): void
    {
        $this->service->create('dup-role', 'Dup Role');

        $this->expectException(\InvalidArgumentException::class);
        $this->service->create('dup-role', 'Another Name');
    }

    #[Test]
    public function it_updates_admin_role(): void
    {
        $role = $this->service->create('upd-role', 'Old Name');

        $updated = $this->service->update(
            $role->admin_role_id,
            name: 'New Name',
            description: 'Updated desc',
            status: 1
        );

        $this->assertEquals('New Name', $updated->name);
        $this->assertEquals('Updated desc', $updated->description);
    }

    #[Test]
    public function it_soft_deletes_admin_role(): void
    {
        $role = $this->service->create('del-role', 'To Delete');

        $this->service->softDelete($role->admin_role_id);

        $this->assertSoftDeleted('admin_roles', [
            'admin_role_id' => $role->admin_role_id,
        ]);
    }

    #[Test]
    public function it_assigns_permissions_to_role(): void
    {
        $role = $this->service->create('perm-role', 'With Perms');

        // admin_permission_id is not fillable; let HasUuids generate it
        $permission = AdminPermission::create([
            'code'        => 'saas-admin.test.view',
            'name'        => 'Test View',
            'module_name' => 'SaasAdmin',
        ]);

        $updated = $this->service->assignPermissions(
            $role->admin_role_id,
            [$permission->admin_permission_id]
        );

        $this->assertTrue(
            $updated->permissions->contains('admin_permission_id', $permission->admin_permission_id)
        );
    }

    #[Test]
    public function it_lists_active_admin_roles(): void
    {
        $this->service->create('list-a', 'List A');
        $this->service->create('list-b', 'List B');

        $list = $this->service->list();

        $this->assertGreaterThanOrEqual(2, $list->count());
    }
}
