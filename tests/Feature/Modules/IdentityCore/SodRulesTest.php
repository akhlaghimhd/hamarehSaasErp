<?php

namespace Tests\Feature\Modules\IdentityCore;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantSodRule;
use App\Modules\IdentityCore\Services\SodService;
use App\Base\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SodRulesTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected TenantRole $roleA;
    protected TenantRole $roleB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'SOD_TEST',
            'status'      => 1,
        ]);

        $this->user = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->user->user_id,
            'status'    => 1,
            'is_owner'  => true,
        ]);

        $this->roleA = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'ap-clerk',
            'name'      => 'AP Clerk',
            'status'    => 1,
        ]);

        $this->roleB = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'ap-approver',
            'name'      => 'AP Approver',
            'status'    => 1,
        ]);

        // Canonical order for pair uniqueness: smaller uuid first
        $a = (string) $this->roleA->tenant_role_id;
        $b = (string) $this->roleB->tenant_role_id;
        if (strcmp($a, $b) > 0) {
            [$this->roleA, $this->roleB] = [$this->roleB, $this->roleA];
        }

        app()->instance('current_tenant_id', $this->tenant->tenant_id);
        app(TenantContext::class)->setTenantId($this->tenant->tenant_id);
    }

    #[Test]
    public function evaluate_detects_block_conflict(): void
    {
        TenantSodRule::create([
            'sod_rule_id' => (string) Str::uuid(),
            'tenant_id'   => $this->tenant->tenant_id,
            'role_a_id'   => $this->roleA->tenant_role_id,
            'role_b_id'   => $this->roleB->tenant_role_id,
            'code'        => 'ap-sod-1',
            'name'        => 'AP clerk vs approver',
            'severity'    => TenantSodRule::SEVERITY_CRITICAL,
            'enforcement' => TenantSodRule::ENFORCEMENT_BLOCK,
            'is_active'   => true,
        ]);

        $result = app(SodService::class)->evaluateRoleSet($this->tenant->tenant_id, [
            $this->roleA->tenant_role_id,
            $this->roleB->tenant_role_id,
        ]);

        $this->assertTrue($result['has_block']);
        $this->assertCount(1, $result['conflicts']);
    }

    #[Test]
    public function assert_assignable_throws_on_block(): void
    {
        TenantSodRule::create([
            'sod_rule_id' => (string) Str::uuid(),
            'tenant_id'   => $this->tenant->tenant_id,
            'role_a_id'   => $this->roleA->tenant_role_id,
            'role_b_id'   => $this->roleB->tenant_role_id,
            'code'        => 'ap-sod-2',
            'name'        => 'AP conflict',
            'severity'    => 4,
            'enforcement' => TenantSodRule::ENFORCEMENT_BLOCK,
            'is_active'   => true,
        ]);

        $this->expectException(HttpException::class);

        app(SodService::class)->assertAssignable($this->tenant->tenant_id, [
            $this->roleA->tenant_role_id,
            $this->roleB->tenant_role_id,
        ]);
    }

    #[Test]
    public function single_role_has_no_conflict(): void
    {
        TenantSodRule::create([
            'sod_rule_id' => (string) Str::uuid(),
            'tenant_id'   => $this->tenant->tenant_id,
            'role_a_id'   => $this->roleA->tenant_role_id,
            'role_b_id'   => $this->roleB->tenant_role_id,
            'code'        => 'ap-sod-3',
            'name'        => 'AP conflict',
            'severity'    => 4,
            'enforcement' => TenantSodRule::ENFORCEMENT_BLOCK,
            'is_active'   => true,
        ]);

        $result = app(SodService::class)->evaluateRoleSet($this->tenant->tenant_id, [
            $this->roleA->tenant_role_id,
        ]);

        $this->assertFalse($result['has_block']);
        $this->assertSame([], $result['conflicts']);
    }

    #[Test]
    public function warn_enforcement_does_not_throw(): void
    {
        TenantSodRule::create([
            'sod_rule_id' => (string) Str::uuid(),
            'tenant_id'   => $this->tenant->tenant_id,
            'role_a_id'   => $this->roleA->tenant_role_id,
            'role_b_id'   => $this->roleB->tenant_role_id,
            'code'        => 'ap-sod-warn',
            'name'        => 'AP soft conflict',
            'severity'    => 2,
            'enforcement' => TenantSodRule::ENFORCEMENT_WARN,
            'is_active'   => true,
        ]);

        $warnings = app(SodService::class)->assertAssignable($this->tenant->tenant_id, [
            $this->roleA->tenant_role_id,
            $this->roleB->tenant_role_id,
        ]);

        $this->assertCount(1, $warnings);
        $this->assertSame(TenantSodRule::ENFORCEMENT_WARN, $warnings[0]['enforcement']);
    }
}
