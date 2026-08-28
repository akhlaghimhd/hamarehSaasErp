<?php

namespace Tests\Feature\Modules\IdentityCore;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Base\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * F1 – Database-level RLS isolation test for tenant_roles
 *
 * Proves that even when Laravel Global Scopes are completely bypassed
 * (withoutGlobalScopes), PostgreSQL RLS still prevents Data Bleed.
 *
 * Required by Tenant Isolation Architecture Standard §4 and §10.
 */
class RlsTenantRolesIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected string $roleAId;
    protected string $roleBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'RLS_A']);
        $this->tenantB = Tenant::factory()->create(['tenant_code' => 'RLS_B']);

        // Insert roles bypassing any application scopes
        $this->roleAId = (string) Str::uuid();
        $this->roleBId = (string) Str::uuid();

        DB::table('tenant_roles')->insert([
            [
                'tenant_role_id'     => $this->roleAId,
                'tenant_id'          => $this->tenantA->tenant_id,
                'code'               => 'ROLE_A',
                'name'               => 'Role A',
                'is_system_default'  => false,
                'status'             => 1,
                'created_at'         => now(),
                'updated_at'         => now(),
                'row_version'        => 1,
            ],
            [
                'tenant_role_id'     => $this->roleBId,
                'tenant_id'          => $this->tenantB->tenant_id,
                'code'               => 'ROLE_B',
                'name'               => 'Role B',
                'is_system_default'  => false,
                'status'             => 1,
                'created_at'         => now(),
                'updated_at'         => now(),
                'row_version'        => 1,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        // Always clear session variable after test
        DB::statement("SELECT set_config('app.current_tenant_id', '', true)");
        TenantContext::resetInstance();
        parent::tearDown();
    }

    /** @test */
    public function rls_blocks_cross_tenant_data_even_when_global_scopes_are_bypassed(): void
    {
        // Simulate what TenantContextMiddleware does
        DB::statement("SELECT set_config('app.current_tenant_id', ?, true)", [
            $this->tenantA->tenant_id,
        ]);

        // Completely bypass Laravel Global Scopes
        $roles = TenantRole::withoutGlobalScopes()->get();

        $this->assertCount(1, $roles);
        $this->assertEquals($this->roleAId, $roles->first()->tenant_role_id);
        $this->assertEquals('ROLE_A', $roles->first()->code);
    }

    /** @test */
    public function rls_returns_empty_when_tenant_context_is_missing(): void
    {
        // No set_config → current_setting returns NULL → policy fails
        DB::statement("SELECT set_config('app.current_tenant_id', '', true)");

        $roles = TenantRole::withoutGlobalScopes()->get();

        $this->assertCount(0, $roles);
    }

    /** @test */
    public function rls_blocks_insert_of_wrong_tenant_id(): void
    {
        DB::statement("SELECT set_config('app.current_tenant_id', ?, true)", [
            $this->tenantA->tenant_id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Attempt to insert a row belonging to tenant B while context is A
        DB::table('tenant_roles')->insert([
            'tenant_role_id'    => (string) Str::uuid(),
            'tenant_id'         => $this->tenantB->tenant_id, // wrong tenant
            'code'              => 'HACK',
            'name'              => 'Should fail',
            'is_system_default' => false,
            'status'            => 1,
            'created_at'        => now(),
            'updated_at'        => now(),
            'row_version'       => 1,
        ]);
    }

    /** @test */
    public function switching_tenant_context_changes_visible_rows(): void
    {
        // Context A
        DB::statement("SELECT set_config('app.current_tenant_id', ?, true)", [
            $this->tenantA->tenant_id,
        ]);
        $this->assertCount(1, TenantRole::withoutGlobalScopes()->get());

        // Switch to Context B
        DB::statement("SELECT set_config('app.current_tenant_id', ?, true)", [
            $this->tenantB->tenant_id,
        ]);
        $rolesB = TenantRole::withoutGlobalScopes()->get();
        $this->assertCount(1, $rolesB);
        $this->assertEquals('ROLE_B', $rolesB->first()->code);
    }
}