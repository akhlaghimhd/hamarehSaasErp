<?php

namespace Tests\Unit\Base;

use App\Base\Context\ScopeContext;
use App\Base\Services\HoldingAccessService;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HoldingAccessServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        Context::flush();
        parent::tearDown();
    }

    #[Test]
    public function owner_is_group_wide(): void
    {
        Context::add('security_context', ['is_owner' => true, 'tenant_id' => 't1']);
        ScopeContext::getInstance()->setScopes([
            ['scope_id' => 's1', 'scope_type' => 'COMPANY', 'reference_id' => 'c1'],
        ]);

        $svc = new HoldingAccessService();
        $this->assertTrue($svc->isGroupWideActor());
    }

    #[Test]
    public function actor_without_company_scopes_is_group_wide(): void
    {
        Context::add('security_context', ['is_owner' => false, 'tenant_id' => 't1']);
        ScopeContext::getInstance()->setScopes([
            ['scope_id' => 's1', 'scope_type' => 'BRANCH', 'reference_id' => 'b1'],
        ]);

        $svc = new HoldingAccessService();
        $this->assertTrue($svc->isGroupWideActor());
        $this->assertSame([], $svc->actorCompanyIds());
    }

    #[Test]
    public function actor_with_company_scopes_is_delegated(): void
    {
        Context::add('security_context', ['is_owner' => false, 'tenant_id' => 't1']);
        ScopeContext::getInstance()->setScopes([
            ['scope_id' => 's1', 'scope_type' => 'COMPANY', 'reference_id' => 'c-a'],
            ['scope_id' => 's2', 'scope_type' => 'COMPANY', 'reference_id' => 'c-b'],
        ]);

        $svc = new HoldingAccessService();
        $this->assertFalse($svc->isGroupWideActor());
        $this->assertEqualsCanonicalizing(['c-a', 'c-b'], $svc->actorCompanyIds());
        $this->assertEqualsCanonicalizing(['s1', 's2'], $svc->actorCompanyScopeIds());
    }
}
