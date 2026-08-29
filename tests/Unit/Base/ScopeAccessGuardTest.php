<?php

namespace Tests\Unit\Base;

use Tests\TestCase;
use App\Base\Context\ScopeContext;
use App\Base\Services\ScopeAccessGuard;
use RuntimeException;
use Illuminate\Support\Str;

class ScopeAccessGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        parent::tearDown();
    }

    /** @test */
    public function gradual_allows_when_user_has_no_scopes_of_type(): void
    {
        config(['scope.enforcement_mode' => 'gradual']);

        ScopeContext::getInstance()->setScopes([]);

        $guard = new ScopeAccessGuard();

        $this->assertTrue($guard->canAccess('BRANCH', (string) Str::uuid()));
        $guard->assertAccess('BRANCH', (string) Str::uuid());
    }

    /** @test */
    public function gradual_denies_when_reference_not_in_user_scopes(): void
    {
        config(['scope.enforcement_mode' => 'gradual']);

        $allowed = (string) Str::uuid();
        $denied = (string) Str::uuid();

        ScopeContext::getInstance()->setScopes([
            [
                'scope_id'     => (string) Str::uuid(),
                'scope_type'   => 'BRANCH',
                'reference_id' => $allowed,
            ],
        ]);

        $guard = new ScopeAccessGuard();

        $this->assertTrue($guard->canAccess('BRANCH', $allowed));
        $this->assertFalse($guard->canAccess('BRANCH', $denied));

        $this->expectException(RuntimeException::class);
        $guard->assertAccess('BRANCH', $denied);
    }

    /** @test */
    public function gradual_denies_when_scope_type_exists_but_reference_ids_empty(): void
    {
        config(['scope.enforcement_mode' => 'gradual']);

        ScopeContext::getInstance()->setScopes([
            [
                'scope_id'     => (string) Str::uuid(),
                'scope_type'   => 'BRANCH',
                'reference_id' => null,
            ],
        ]);

        $guard = new ScopeAccessGuard();

        $this->assertFalse($guard->canAccess('BRANCH', (string) Str::uuid()));
    }

    /** @test */
    public function strict_denies_when_user_has_no_scopes_of_strict_type(): void
    {
        config(['scope.enforcement_mode' => 'strict']);

        ScopeContext::getInstance()->setScopes([]);

        $guard = new ScopeAccessGuard();

        $this->assertFalse($guard->canAccess('BRANCH', (string) Str::uuid()));

        $this->expectException(RuntimeException::class);
        $guard->assertAccess('WAREHOUSE', (string) Str::uuid());
    }

    /** @test */
    public function strict_allows_listed_reference_when_scopes_present(): void
    {
        config(['scope.enforcement_mode' => 'strict']);

        $allowed = (string) Str::uuid();

        ScopeContext::getInstance()->setScopes([
            [
                'scope_id'     => (string) Str::uuid(),
                'scope_type'   => 'COMPANY',
                'reference_id' => $allowed,
            ],
        ]);

        $guard = new ScopeAccessGuard();

        $this->assertTrue($guard->canAccess('COMPANY', $allowed));
        $guard->assertAccess('COMPANY', $allowed);
    }

    /** @test */
    public function cost_center_type_is_not_in_default_strict_list(): void
    {
        config([
            'scope.enforcement_mode' => 'strict',
            'scope.strict_scope_types' => ['COMPANY', 'BRANCH', 'WAREHOUSE'],
        ]);

        ScopeContext::getInstance()->setScopes([]);

        $guard = new ScopeAccessGuard();

        // COST_CENTER not in strict list → gradual-like allow when no scopes of type
        $this->assertTrue($guard->canAccess('COST_CENTER', (string) Str::uuid()));
    }
}
