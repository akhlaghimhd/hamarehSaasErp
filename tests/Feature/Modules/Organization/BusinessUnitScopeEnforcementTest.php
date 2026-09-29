<?php

namespace Tests\Feature\Modules\Organization;

use App\Base\Context\ScopeContext;
use App\Modules\Organization\Models\BusinessUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessUnitScopeEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId;

    private string $buAllowed;

    private string $buDenied;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (string) Str::uuid();
        DB::table('tenants')->insert([
            'tenant_id'   => $this->tenantId,
            'tenant_code' => 'BUS1',
            'tenant_name' => 'BU Scope Tenant',
            'slug'        => 'bu-scope-tenant',
            'status'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantId]);
        app()->instance('current_tenant_id', $this->tenantId);

        $this->buAllowed = (string) Str::uuid();
        $this->buDenied = (string) Str::uuid();

        foreach ([
            [$this->buAllowed, 'BU-A', 'Allowed'],
            [$this->buDenied, 'BU-B', 'Denied'],
        ] as [$id, $code, $name]) {
            DB::table('erp_business_units')->insert([
                'business_unit_id' => $id,
                'tenant_id'        => $this->tenantId,
                'code'             => $code,
                'name'             => $name,
                'is_active'        => true,
                'row_version'      => 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        ScopeContext::resetInstance();
        Context::flush();
    }

    private function setScopes(array $referenceIds, bool $isOwner = false): void
    {
        $scopes = array_map(fn ($ref) => [
            'scope_id'     => (string) Str::uuid(),
            'scope_type'   => 'BUSINESS_UNIT',
            'reference_id' => $ref,
        ], $referenceIds);

        ScopeContext::getInstance()->setScopes($scopes);
        Context::add('user_scopes', $scopes);
        Context::add('security_context', [
            'is_owner' => $isOwner,
            'scopes'   => $scopes,
        ]);
        app()->instance('current_security_context', [
            'is_owner' => $isOwner,
            'scopes'   => $scopes,
        ]);
        app()->instance('current_user_scopes', $scopes);
    }

    #[Test]
    public function list_filters_to_allowed_business_units(): void
    {
        $this->setScopes([$this->buAllowed]);

        $ids = BusinessUnit::query()
            ->where('tenant_id', $this->tenantId)
            ->pluck('business_unit_id')
            ->all();

        $this->assertContains($this->buAllowed, $ids);
        $this->assertNotContains($this->buDenied, $ids);
    }

    #[Test]
    public function owner_sees_all_business_units(): void
    {
        $this->setScopes([$this->buAllowed], isOwner: true);

        $ids = BusinessUnit::query()
            ->where('tenant_id', $this->tenantId)
            ->pluck('business_unit_id')
            ->all();

        $this->assertContains($this->buAllowed, $ids);
        $this->assertContains($this->buDenied, $ids);
    }

    #[Test]
    public function assert_access_denies_unscoped_bu(): void
    {
        $this->setScopes([$this->buAllowed]);

        $this->assertTrue(BusinessUnit::currentUserHasAccessTo($this->buAllowed));
        $this->assertFalse(BusinessUnit::currentUserHasAccessTo($this->buDenied));
    }

    #[Test]
    public function without_bu_scopes_gradual_sees_all(): void
    {
        config(['scope.enforcement_mode' => 'gradual']);
        ScopeContext::getInstance()->setScopes([]);
        Context::add('security_context', ['is_owner' => false, 'scopes' => []]);
        app()->instance('current_security_context', ['is_owner' => false, 'scopes' => []]);

        $count = BusinessUnit::query()->where('tenant_id', $this->tenantId)->count();
        $this->assertSame(2, $count);
    }

    #[Test]
    public function without_bu_scopes_strict_sees_none(): void
    {
        config(['scope.enforcement_mode' => 'strict']);
        ScopeContext::getInstance()->setScopes([]);
        Context::add('security_context', ['is_owner' => false, 'scopes' => []]);
        app()->instance('current_security_context', ['is_owner' => false, 'scopes' => []]);

        $count = BusinessUnit::query()->where('tenant_id', $this->tenantId)->count();
        $this->assertSame(0, $count);
    }
}
