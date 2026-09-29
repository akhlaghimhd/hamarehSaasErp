<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Modules\IdentityCore\Services\ScimUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ScimUserServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId;

    private ScimUserService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (string) Str::uuid();
        DB::table('tenants')->insert([
            'tenant_id'   => $this->tenantId,
            'tenant_code' => 'SCM1',
            'tenant_name' => 'SCIM Tenant',
            'slug'        => 'scim-tenant',
            'status'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantId]);
        app()->instance('current_tenant_id', $this->tenantId);

        $this->svc = app(ScimUserService::class);
    }

    #[Test]
    public function service_provider_config_has_schemas(): void
    {
        $cfg = $this->svc->serviceProviderConfig();
        $this->assertArrayHasKey('schemas', $cfg);
        $this->assertTrue($cfg['filter']['supported']);
    }

    #[Test]
    public function create_and_get_user(): void
    {
        $created = $this->svc->createUser($this->tenantId, [
            'userName' => 'scim.user@example.com',
            'name' => ['givenName' => 'Scim', 'familyName' => 'User'],
            'emails' => [['value' => 'scim.user@example.com', 'primary' => true]],
            'active' => true,
        ]);

        $this->assertSame(ScimUserService::SCHEMA_USER, $created['schemas'][0]);
        $this->assertTrue($created['active']);
        $this->assertSame('scim.user@example.com', $created['userName']);

        $fetched = $this->svc->getUser($this->tenantId, $created['id']);
        $this->assertSame($created['id'], $fetched['id']);
    }

    #[Test]
    public function list_users_returns_list_response(): void
    {
        $this->svc->createUser($this->tenantId, [
            'userName' => 'a@example.com',
            'emails' => [['value' => 'a@example.com', 'primary' => true]],
        ]);
        $this->svc->createUser($this->tenantId, [
            'userName' => 'b@example.com',
            'emails' => [['value' => 'b@example.com', 'primary' => true]],
        ]);

        $list = $this->svc->listUsers($this->tenantId, 1, 10);
        $this->assertSame(ScimUserService::SCHEMA_LIST, $list['schemas'][0]);
        $this->assertGreaterThanOrEqual(2, $list['totalResults']);
        $this->assertNotEmpty($list['Resources']);
    }

    #[Test]
    public function deactivate_via_replace(): void
    {
        $created = $this->svc->createUser($this->tenantId, [
            'userName' => 'deact@example.com',
            'emails' => [['value' => 'deact@example.com', 'primary' => true]],
            'active' => true,
        ]);

        $updated = $this->svc->replaceUser($this->tenantId, $created['id'], [
            'active' => false,
        ]);

        $this->assertFalse($updated['active']);
    }

    #[Test]
    public function delete_soft_removes_membership(): void
    {
        $created = $this->svc->createUser($this->tenantId, [
            'userName' => 'del@example.com',
            'emails' => [['value' => 'del@example.com', 'primary' => true]],
        ]);

        $this->svc->deleteUser($this->tenantId, $created['id']);

        $this->expectException(HttpException::class);
        $this->svc->getUser($this->tenantId, $created['id']);
    }

    #[Test]
    public function create_requires_username(): void
    {
        $this->expectException(HttpException::class);
        $this->svc->createUser($this->tenantId, ['active' => true]);
    }
}
