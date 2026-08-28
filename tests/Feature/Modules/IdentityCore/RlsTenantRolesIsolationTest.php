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
 * F1 – Database-level RLS isolation for tenant_roles
 *
 * Proves Isolation §4 + §10:
 * - even with withoutGlobalScopes(), DB still blocks Data Bleed
 * - only when connection role is non-superuser (NOBYPASSRLS)
 *
 * Note: Laravel test DB uses RefreshDatabase → tables recreated each run.
 * Grants + policy are re-applied in setUp so RLS remains enforceable.
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

        // Ensure app_user can access tables created by RefreshDatabase
        // and RLS policy is present with safe empty-string handling.
        $this->ensureRlsInfrastructure();

        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'RLS_A']);
        $this->tenantB = Tenant::factory()->create(['tenant_code' => 'RLS_B']);

        $this->roleAId = (string) Str::uuid();
        $this->roleBId = (string) Str::uuid();

        // Seed as current role (superuser) so data exists regardless of RLS
        DB::table('tenant_roles')->insert([
            [
                'tenant_role_id'    => $this->roleAId,
                'tenant_id'         => $this->tenantA->tenant_id,
                'code'              => 'ROLE_A',
                'name'              => 'Role A',
                'is_system_default' => false,
                'status'            => 1,
                'created_at'        => now(),
                'updated_at'        => now(),
                'row_version'       => 1,
            ],
            [
                'tenant_role_id'    => $this->roleBId,
                'tenant_id'         => $this->tenantB->tenant_id,
                'code'              => 'ROLE_B',
                'name'              => 'Role B',
                'is_system_default' => false,
                'status'            => 1,
                'created_at'        => now(),
                'updated_at'        => now(),
                'row_version'       => 1,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        try {
            DB::statement('RESET ROLE');
            DB::statement("SELECT set_config('app.current_tenant_id', '', true)");
        } catch (\Throwable $e) {
            // ignore
        }

        TenantContext::resetInstance();
        parent::tearDown();
    }

    /**
     * Re-apply grants + RLS policy after RefreshDatabase recreates tables.
     * Does not weaken isolation; makes non-superuser role able to exercise RLS.
     */
    protected function ensureRlsInfrastructure(): void
    {
        // Privileges for app_user on current (testing) database objects
        DB::statement('GRANT USAGE ON SCHEMA public TO app_user');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO app_user');
        DB::statement('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO app_user');

        // RLS on tenant_roles
        DB::statement('ALTER TABLE tenant_roles ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_roles FORCE ROW LEVEL SECURITY');
        
        DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON tenant_roles');

        DB::statement("
            CREATE POLICY tenant_isolation_policy ON tenant_roles
            FOR ALL
            USING (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
            WITH CHECK (
                tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
            )
        ");
    }

    /**
     * Enforce RLS by switching to non-superuser role (NOBYPASSRLS).
     */
    protected function actAsAppUser(): void
    {
        DB::statement('SET ROLE app_user');
    }

    /** @test */
    public function rls_blocks_cross_tenant_data_even_when_global_scopes_are_bypassed(): void
    {
        $this->actAsAppUser();

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [
            $this->tenantA->tenant_id,
        ]);

        $roles = TenantRole::withoutGlobalScopes()->get();

        $this->assertCount(1, $roles);
        $this->assertEquals($this->roleAId, $roles->first()->tenant_role_id);
        $this->assertEquals('ROLE_A', $roles->first()->code);
    }

    /** @test */
    public function rls_returns_empty_when_tenant_context_is_missing(): void
    {
        $this->actAsAppUser();

        DB::statement("SELECT set_config('app.current_tenant_id', '', false)");

        $roles = TenantRole::withoutGlobalScopes()->get();

        $this->assertCount(0, $roles);
    }

    /** @test */
    public function rls_blocks_insert_of_wrong_tenant_id(): void
    {
        $this->actAsAppUser();

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [
            $this->tenantA->tenant_id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('tenant_roles')->insert([
            'tenant_role_id'    => (string) Str::uuid(),
            'tenant_id'         => $this->tenantB->tenant_id,
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
        $this->actAsAppUser();

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [
            $this->tenantA->tenant_id,
        ]);
        $this->assertCount(1, TenantRole::withoutGlobalScopes()->get());

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [
            $this->tenantB->tenant_id,
        ]);
        $rolesB = TenantRole::withoutGlobalScopes()->get();
        $this->assertCount(1, $rolesB);
        $this->assertEquals('ROLE_B', $rolesB->first()->code);
    }
}