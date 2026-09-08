<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Modules\SaasAdmin\Models\AdminUser;
use App\Modules\SaasAdmin\Services\AdminUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminUserCrudTest extends TestCase
{
    use RefreshDatabase;

    private AdminUserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AdminUserService::class);
    }

    #[Test]
    public function it_creates_admin_user(): void
    {
        $user = $this->service->create(
            username: 'superadmin',
            email: 'super@example.com',
            password: 'SecurePass123!',
            firstName: 'Super',
            lastName: 'Admin'
        );

        $this->assertNotNull($user->admin_user_id);
        $this->assertEquals('superadmin', $user->username);
        $this->assertEquals('super@example.com', $user->email);
        $this->assertEquals(1, $user->status);
        $this->assertDatabaseHas('admin_users', [
            'username' => 'superadmin',
            'email'    => 'super@example.com',
        ]);
    }

    #[Test]
    public function it_rejects_duplicate_username(): void
    {
        $this->service->create('dupuser', 'a@example.com', 'pass12345');

        $this->expectException(\InvalidArgumentException::class);
        $this->service->create('dupuser', 'b@example.com', 'pass12345');
    }

    #[Test]
    public function it_updates_admin_user(): void
    {
        $user = $this->service->create('upduser', 'upd@example.com', 'pass12345', 'Old');

        $updated = $this->service->update(
            $user->admin_user_id,
            firstName: 'New',
            lastName: 'Name',
            status: 1
        );

        $this->assertEquals('New', $updated->first_name);
        $this->assertEquals('Name', $updated->last_name);
    }

    #[Test]
    public function it_soft_deletes_admin_user(): void
    {
        $user = $this->service->create('deluser', 'del@example.com', 'pass12345');

        $this->service->softDelete($user->admin_user_id);

        $this->assertSoftDeleted('admin_users', [
            'admin_user_id' => $user->admin_user_id,
        ]);
    }

    #[Test]
    public function it_lists_active_admin_users(): void
    {
        $this->service->create('list1', 'l1@example.com', 'pass12345');
        $this->service->create('list2', 'l2@example.com', 'pass12345');

        $list = $this->service->list();

        $this->assertGreaterThanOrEqual(2, $list->count());
    }
}
