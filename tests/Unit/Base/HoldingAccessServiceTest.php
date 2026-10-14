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
    public function owner_is_group_wide_for_identity_visibility_but_not_operator(): void
    {
        Context::add('security_context', ['is_owner' => true, 'tenant_id' => 't1']);
        ScopeContext::getInstance()->setScopes([
            ['scope_id' => 's1', 'scope_type' => 'COMPANY', 'reference_id' => 'c1'],
        ]);

        $svc = new HoldingAccessService();

        $this->assertTrue($svc->isTechnicalOwner());
        $this->assertSame(HoldingAccessService::ACTOR_TECHNICAL_OWNER, $svc->resolveActorKind());
        // Identity list/membership visibility still group-wide for technical owner.
        $this->assertTrue($svc->isGroupWideActor());
        // IDORG-T01 / FIN-HOLD-L06: owner is NOT silent super-operator on all companies.
        $this->assertFalse($svc->isGroupWideOperator());
        $this->assertFalse($svc->isSoftSmePath());
        $this->assertFalse($svc->isCompanyOperator());
    }

    #[Test]
    public function actor_without_company_scopes_is_soft_sme_group_wide_and_operator(): void
    {
        Context::add('security_context', ['is_owner' => false, 'tenant_id' => 't1']);
        ScopeContext::getInstance()->setScopes([
            ['scope_id' => 's1', 'scope_type' => 'BRANCH', 'reference_id' => 'b1'],
        ]);

        $svc = new HoldingAccessService();

        $this->assertFalse($svc->isTechnicalOwner());
        $this->assertTrue($svc->isSoftSmePath());
        $this->assertSame(HoldingAccessService::ACTOR_SOFT_SME, $svc->resolveActorKind());
        $this->assertTrue($svc->isGroupWideActor());
        $this->assertTrue($svc->isGroupWideOperator());
        $this->assertSame([], $svc->actorCompanyIds());
    }

    #[Test]
    public function actor_with_company_scopes_is_delegated_company_operator(): void
    {
        Context::add('security_context', ['is_owner' => false, 'tenant_id' => 't1']);
        ScopeContext::getInstance()->setScopes([
            ['scope_id' => 's1', 'scope_type' => 'COMPANY', 'reference_id' => 'c-a'],
            ['scope_id' => 's2', 'scope_type' => 'COMPANY', 'reference_id' => 'c-b'],
        ]);

        $svc = new HoldingAccessService();

        $this->assertFalse($svc->isTechnicalOwner());
        $this->assertTrue($svc->isCompanyOperator());
        $this->assertSame(HoldingAccessService::ACTOR_COMPANY_OPERATOR, $svc->resolveActorKind());
        $this->assertFalse($svc->isGroupWideActor());
        $this->assertFalse($svc->isGroupWideOperator());
        $this->assertFalse($svc->isSoftSmePath());
        $this->assertEqualsCanonicalizing(['c-a', 'c-b'], $svc->actorCompanyIds());
        $this->assertEqualsCanonicalizing(['s1', 's2'], $svc->actorCompanyScopeIds());
    }

    #[Test]
    public function group_observer_placeholder_is_false_until_wave1(): void
    {
        Context::add('security_context', ['is_owner' => false, 'tenant_id' => 't1']);
        ScopeContext::getInstance()->setScopes([]);

        $svc = new HoldingAccessService();

        $this->assertFalse($svc->isGroupObserver());
    }
}
