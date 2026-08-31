<?php

namespace Tests\Feature\Modules\IdentityCore;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantScope;
use App\Modules\IdentityCore\Models\TenantUserScope;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Branch;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use App\Base\Http\Middleware\RequireScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use PHPUnit\Framework\Attributes\Test;

/**
 * F3 — Validate Scope on actions.
 *
 * Covers:
 * - Branch::assertCurrentUserHasAccessTo() (hard assert)
 * - RequireScope middleware (route-level)
 * - Alignment with F2 gradual / strict policy
 */
class ScopeActionValidationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected TenantUser $tenantUser;
    protected Company $company;
    protected Branch $branchAllowed;
    protected Branch $branchDenied;

    protected function setUp(): void
    {
        parent::setUp();

        config(['scope.enforcement_mode' => 'gradual']);

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'SCOPE_F3',
            'status'      => 1,
        ]);

        $this->user = User::factory()->create(['status' => 1]);

        $this->tenantUser = TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->user->user_id,
            'status'    => 1,
        ]);

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);

        $this->company = Company::withoutGlobalScopes()->create([
            'company_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'C-F3',
            'name'       => 'F3 Company',
            'is_active'  => true,
        ]);

        $this->branchAllowed = Branch::withoutGlobalScopes()->create([
            'branch_id'  => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'company_id' => $this->company->company_id,
            'code'       => 'BR-OK',
            'name'       => 'Allowed',
            'is_active'  => true,
        ]);

        $this->branchDenied = Branch::withoutGlobalScopes()->create([
            'branch_id'  => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'company_id' => $this->company->company_id,
            'code'       => 'BR-NO',
            'name'       => 'Denied',
            'is_active'  => true,
        ]);

        $scope = TenantScope::withoutGlobalScopes()->create([
            'scope_id'     => (string) Str::uuid(),
            'tenant_id'    => $this->tenant->tenant_id,
            'scope_name'   => 'Allowed Branch Scope',
            'scope_type'   => 'BRANCH',
            'reference_id' => $this->branchAllowed->branch_id,
            'is_active'    => true,
        ]);

        TenantUserScope::withoutGlobalScopes()->create([
            'assignment_id'  => (string) Str::uuid(),
            'tenant_id'      => $this->tenant->tenant_id,
            'tenant_user_id' => $this->tenantUser->tenant_user_id,
            'scope_id'       => $scope->scope_id,
        ]);

        ScopeContext::getInstance()->setScopes([
            [
                'scope_id'     => $scope->scope_id,
                'scope_name'   => 'Allowed Branch Scope',
                'scope_type'   => 'BRANCH',
                'reference_id' => $this->branchAllowed->branch_id,
            ],
        ], $this->tenantUser->tenant_user_id);
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    #[Test]
    public function assert_allows_access_to_scoped_reference(): void
    {
        Branch::assertCurrentUserHasAccessTo($this->branchAllowed->branch_id);

        $this->assertTrue(true);
    }

    #[Test]
    public function assert_denies_access_to_out_of_scope_reference(): void
    {
        $this->expectException(HttpException::class);

        Branch::assertCurrentUserHasAccessTo($this->branchDenied->branch_id);
    }

    #[Test]
    public function assert_in_strict_mode_denies_when_user_has_no_scopes(): void
    {
        config(['scope.enforcement_mode' => 'strict']);

        ScopeContext::resetInstance();
        ScopeContext::getInstance()->setScopes([], $this->tenantUser->tenant_user_id);

        $this->expectException(HttpException::class);

        Branch::assertCurrentUserHasAccessTo($this->branchAllowed->branch_id);
    }

    #[Test]
    public function assert_in_gradual_mode_allows_when_user_has_no_scopes(): void
    {
        config(['scope.enforcement_mode' => 'gradual']);

        ScopeContext::resetInstance();
        ScopeContext::getInstance()->setScopes([], $this->tenantUser->tenant_user_id);

        Branch::assertCurrentUserHasAccessTo($this->branchAllowed->branch_id);

        $this->assertTrue(true);
    }

    #[Test]
    public function middleware_allows_request_when_reference_is_in_scope(): void
    {
        $request = Request::create('/branches/' . $this->branchAllowed->branch_id, 'GET');
        $request->setRouteResolver(function () use ($request) {
            $route = new Route(['GET'], '/branches/{branch}', []);
            $route->bind($request);
            $route->setParameter('branch', $this->branchAllowed->branch_id);

            return $route;
        });

        $middleware = new RequireScope();

        $response = $middleware->handle($request, function () {
            return response()->json(['ok' => true], 200);
        }, 'BRANCH', 'branch');

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function middleware_denies_request_when_reference_is_out_of_scope(): void
    {
        $request = Request::create('/branches/' . $this->branchDenied->branch_id, 'GET');
        $request->setRouteResolver(function () use ($request) {
            $route = new Route(['GET'], '/branches/{branch}', []);
            $route->bind($request);
            $route->setParameter('branch', $this->branchDenied->branch_id);

            return $route;
        });

        $middleware = new RequireScope();

        $response = $middleware->handle($request, function () {
            return response()->json(['ok' => true], 200);
        }, 'BRANCH', 'branch');

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Scope', $response->getContent());
    }

    #[Test]
    public function middleware_in_strict_mode_denies_when_no_scopes_assigned(): void
    {
        config(['scope.enforcement_mode' => 'strict']);

        ScopeContext::resetInstance();
        ScopeContext::getInstance()->setScopes([], $this->tenantUser->tenant_user_id);

        $request = Request::create('/branches/' . $this->branchAllowed->branch_id, 'GET');
        $request->setRouteResolver(function () use ($request) {
            $route = new Route(['GET'], '/branches/{branch}', []);
            $route->bind($request);
            $route->setParameter('branch', $this->branchAllowed->branch_id);

            return $route;
        });

        $middleware = new RequireScope();

        $response = $middleware->handle($request, function () {
            return response()->json(['ok' => true], 200);
        }, 'BRANCH', 'branch');

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function middleware_in_gradual_mode_allows_when_no_scopes_assigned(): void
    {
        config(['scope.enforcement_mode' => 'gradual']);

        ScopeContext::resetInstance();
        ScopeContext::getInstance()->setScopes([], $this->tenantUser->tenant_user_id);

        $request = Request::create('/branches/' . $this->branchAllowed->branch_id, 'GET');
        $request->setRouteResolver(function () use ($request) {
            $route = new Route(['GET'], '/branches/{branch}', []);
            $route->bind($request);
            $route->setParameter('branch', $this->branchAllowed->branch_id);

            return $route;
        });

        $middleware = new RequireScope();

        $response = $middleware->handle($request, function () {
            return response()->json(['ok' => true], 200);
        }, 'BRANCH', 'branch');

        $this->assertEquals(200, $response->getStatusCode());
    }
}
