<?php

namespace Tests\Feature\Modules\IdentityCore;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\User;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Branch;
use App\Base\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * F1 extension – RLS on multiple tenant_id tables
 * Proves Isolation §4 / §10 for tenant_permissions, tenant_users, erp_branches
 * without weakening withoutGlobalScopes checks.
 */
class RlsMultiTableIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected string $permAId;
    protected string $permBId;
    protected string $branchAId;
    protected string $branchBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureRlsInfrastructure([
            'tenant_permissions',
            'tenant_users',
            'erp_branches',
            'tenant_roles',
            'erp_companies',
        ]);

        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'RLS_MT_A']);
        $this->tenantB = Tenant::factory()->create(['tenant_code' => 'RLS_MT_B']);

        $this->permAId = (string) Str::uuid();
        $this->permBId = (string) Str::uuid();

        DB::table('tenant_permissions')->insert([
            [
                'tenant_permission_id' => $this->permAId,
                'tenant_id'            => $this->tenantA->tenant_id,
                'code'                 => 'perm.a',
                'name'                 => 'Perm A',
                'module_name'          => 'Identity',
                'status'               => 1,
                'created_at'           => now(),
                'updated_at'           => now(),
                'row_version'          => 1,
            ],
            [
                'tenant_permission_id' => $this->permBId,
                'tenant_id'            => $this->tenantB->tenant_id,
                'code'                 => 'perm.b',
                'name'                 => 'Perm B',
                'module_name'          => 'Identity',
                'status'               => 1,
                'created_at'           => now(),
                'updated_at'           => now(),
                'row_version'          => 1,
            ],
        ]);

        $companyAId = (string) Str::uuid();
        $companyBId = (string) Str::uuid();

        DB::table('erp_companies')->insert([
            [
                'company_id' => $companyAId,
                'tenant_id'  => $this->tenantA->tenant_id,
                'code'       => 'CA',
                'name'       => 'Company A',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
                'row_version'=> 1,
            ],
            [
                'company_id' => $companyBId,
                'tenant_id'  => $this->tenantB->tenant_id,
                'code'       => 'CB',
                'name'       => 'Company B',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
                'row_version'=> 1,
            ],
        ]);

        $this->branchAId = (string) Str::uuid();
        $this->branchBId = (string) Str::uuid();

        DB::table('erp_branches')->insert([
            [
                'branch_id'  => $this->branchAId,
                'tenant_id'  => $this->tenantA->tenant_id,
                'company_id' => $companyAId,
                'code'       => 'BRA',
                'name'       => 'Branch A',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
                'row_version'=> 1,
            ],
            [
                'branch_id'  => $this->branchBId,
                'tenant_id'  => $this->tenantB->tenant_id,
                'company_id' => $companyBId,
                'code'       => 'BRB',
                'name'       => 'Branch B',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
                'row_version'=> 1,
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

    protected function ensureRlsInfrastructure(array $tables): void
    {
        DB::statement('GRANT USAGE ON SCHEMA public TO app_user');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO app_user');
        DB::statement('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO app_user');

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON {$table}
                FOR ALL
                USING (
                    tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
                )
                WITH CHECK (
                    tenant_id = nullif(current_setting('app.current_tenant_id', true), '')::uuid
                )
            ");
        }
    }

    protected function actAsAppUser(): void
    {
        DB::statement('SET ROLE app_user');
    }

    /** @test */
    public function rls_isolates_tenant_permissions_even_without_global_scopes(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantA->tenant_id]);

        $rows = TenantPermission::withoutGlobalScopes()->get();
        $this->assertCount(1, $rows);
        $this->assertEquals($this->permAId, $rows->first()->tenant_permission_id);
    }

    /** @test */
    public function rls_isolates_erp_branches_even_without_global_scopes(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantA->tenant_id]);

        $rows = Branch::withoutGlobalScopes()->get();
        $this->assertCount(1, $rows);
        $this->assertEquals($this->branchAId, $rows->first()->branch_id);
    }

    /** @test */
    public function rls_returns_empty_on_permissions_when_tenant_context_missing(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', '', false)");

        $this->assertCount(0, TenantPermission::withoutGlobalScopes()->get());
        $this->assertCount(0, Branch::withoutGlobalScopes()->get());
    }

    /** @test */
    public function rls_blocks_cross_tenant_insert_on_erp_branches(): void
    {
        $this->actAsAppUser();
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantA->tenant_id]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('erp_branches')->insert([
            'branch_id'  => (string) Str::uuid(),
            'tenant_id'  => $this->tenantB->tenant_id,
            'company_id' => (string) Str::uuid(),
            'code'       => 'HACK',
            'name'       => 'Hack',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
            'row_version'=> 1,
        ]);
    }
}