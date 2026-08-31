<?php

use App\Modules\IdentityCore\DTOs\AssignPermissionsToRoleDTO;
use App\Modules\IdentityCore\DTOs\AssignRoleToUserDTO;
use App\Modules\IdentityCore\DTOs\CreateRoleDTO;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenantId = Str::uuid()->toString();
    app()->instance('current_tenant_id', $this->tenantId);
    $this->service = app(RoleService::class);
});

it('creates a tenant role and writes to outbox', function () {
    $dto = CreateRoleDTO::fromRequest([
        'role_name' => 'ADMIN',
        'description' => 'Full access role',
        'permission_ids' => []
    ]);

    $role = $this->service->createRole($dto);

    expect($role->tenant_id)->toBe($this->tenantId)
        ->and($role->code)->toBe('ADMIN');

    $this->assertDatabaseHas('tenant_roles', [
        'tenant_role_id' => $role->tenant_role_id,
        'tenant_id' => $this->tenantId,
        'code' => 'ADMIN',
    ]);

    $this->assertDatabaseHas('event_outbox', [
        'tenant_id' => $this->tenantId,
        'aggregate_type' => 'tenant_roles',
        'aggregate_id' => $role->tenant_role_id,
        'status' => 1,
        'event_type' => 'identity.role.created.v1'
    ]);
});

it('assigns role to user and logs to outbox', function () {
    $user = User::factory()->create();
    $userId = $user->user_id;

    $role = $this->service->createRole(CreateRoleDTO::fromRequest([
        'role_name' => 'MANAGER',
        'permission_ids' => []
    ]));

    $dto = AssignRoleToUserDTO::fromRequest([
        'user_id' => $userId,
        'role_ids' => [$role->tenant_role_id]
    ]);

    $assignment = $this->service->assignRoleToUser($dto);

    expect($assignment->user_id)->toBe($userId);

    $this->assertDatabaseHas('tenant_user_roles', [
        'tenant_id' => $this->tenantId,
        'user_id' => $userId,
        'tenant_role_id' => $role->tenant_role_id
    ]);

    $this->assertDatabaseHas('event_outbox', [
        'event_type' => 'identity.role.assigned.v1',
        'aggregate_type' => 'tenant_user_roles'
    ]);
});

it('assigns permissions to role and logs to outbox', function () {
    $permission = TenantPermission::create([
        'tenant_id' => $this->tenantId,
        'code' => 'inventory.items.create',
        'name' => 'Create Inventory Item',
        'module_name' => 'Inventory',
        'description' => 'Create Inventory Item',
        'status' => 1,
    ]);

    $role = $this->service->createRole(CreateRoleDTO::fromRequest([
        'role_name' => 'INVENTORY_CLERK',
        'permission_ids' => []
    ]));

    $dto = AssignPermissionsToRoleDTO::fromRequest([
        'tenant_role_id' => $role->tenant_role_id,
        'permission_ids' => [$permission->tenant_permission_id]
    ]);

    $this->service->assignPermissionsToRole($dto);

    $this->assertDatabaseHas('tenant_role_permissions', [
        'tenant_id' => $this->tenantId,
        'tenant_role_id' => $role->tenant_role_id,
        'tenant_permission_id' => $permission->tenant_permission_id
    ]);
});

it('prevents data bleeding across tenants in role assignment', function () {
    $roleTenantA = $this->service->createRole(CreateRoleDTO::fromRequest([
        'role_name' => 'HR_MANAGER',
        'permission_ids' => []
    ]));

    $tenantIdB = Str::uuid()->toString();
    app()->instance('current_tenant_id', $tenantIdB);

    $userB = User::factory()->create();
    $userIdB = $userB->user_id;

    $dtoB = AssignRoleToUserDTO::fromRequest([
        'user_id' => $userIdB,
        'role_ids' => [$roleTenantA->tenant_role_id]
    ]);

    expect(fn () => $this->service->assignRoleToUser($dtoB))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});
