<?php

namespace Tests\Unit\Base;

use App\Base\Context\ScopeContext;
use App\Base\Services\HoldingAccessService;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class HoldingAccessServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        Context::flush();
        parent::tearDown();
    }

    /**
     * @param  int  $companyCount
     */
    private function serviceWithCompanyCount(int $companyCount): HoldingAccessService
    {
        return new class($companyCount) extends HoldingAccessService {
            public function __construct(private readonly int $forcedCompanyCount)
            {
                parent::__construct();
            }

            protected function tenantActiveCompanyCount(): int
            {
                return $this->forcedCompanyCount;
            }
        };
    }

    /**
     * Force isGroupObserver() true for T03 list-vs-mutate coverage before Wave 1 seed.
     *
     * @param  int  $companyCount
     */
    private function serviceAsGroupObserver(int $companyCount): HoldingAccessService
    {
        return new class($companyCount) extends HoldingAccessService {
            public function __construct(private readonly int $forcedCompanyCount)
            {
                parent::__construct();
            }

            protected function tenantActiveCompanyCount(): int
            {
                return $this->forcedCompanyCount;
            }

            public function isGroupObserver(): bool
            {
                return true;
            }
        };
    }

    #[Test]
    public function owner_is_group_wide_for_identity_visibility_but_not_operator(): void
    {
        Context::add('security_context', ['is_owner' => true, 'tenant_id' => 't1']);
        ScopeContext::getInstance()->setScopes([
            ['scope_id' => 's1', 'scope_type' => 'COMPANY', 'reference_id' => 'c1'],
        ]);

        $svc = $this->serviceWithCompanyCount(3);

        $this->assertTrue($svc->isTechnicalOwner());
        $this->assertSame(HoldingAccessService::ACTOR_TECHNICAL_OWNER, $svc->resolveActorKind());
        $this->assertTrue($svc->isGroupWideActor());
        $this->assertTrue($svc->isIdentityMutateGroupWide());
        $this->assertFalse($svc->isGroupWideOperator());
        $this->assertFalse($svc->isSoftSmePath());
        $this->assertFalse($svc->isCompanyOperator());
    }

    #[Test]
    public function actor_without_company_scopes_on_single_company_is_soft_sme(): void
    {
        Context::add('security_context', ['is_owner' => false, 'tenant_id' => 't1']);
        ScopeContext::getInstance()->setScopes([
            ['scope_id' => 's1', 'scope_type' => 'BRANCH', 'reference_id' => 'b1'],
        ]);

        $svc = $this->serviceWithCompanyCount(1);

        $this->assertFalse($svc->isTechnicalOwner());
        $this->assertTrue($svc->isTenantSingleCompany());
        $this->assertTrue($svc->isSoftSmePath());
        $this->assertSame(HoldingAccessService::ACTOR_SOFT_SME, $svc->resolveActorKind());
        $this->assertTrue($svc->isGroupWideActor());
        $this->assertTrue($svc->isIdentityMutateGroupWide());
        $this->assertTrue($svc->isGroupWideOperator());
        $this->assertSame([], $svc->actorCompanyIds());
    }

    #[Test]
    public function actor_without_company_scopes_on_multi_company_is_not_group_wide(): void
    {
        Context::add('security_context', ['is_owner' => false, 'tenant_id' => 't1']);
        ScopeContext::getInstance()->setScopes([
            ['scope_id' => 's1', 'scope_type' => 'BRANCH', 'reference_id' => 'b1'],
        ]);

        $svc = $this->serviceWithCompanyCount(3);

        $this->assertFalse($svc->isTenantSingleCompany());
        $this->assertFalse($svc->isSoftSmePath());
        $this->assertFalse($svc->isGroupWideActor());
        $this->assertFalse($svc->isIdentityMutateGroupWide());
        $this->assertFalse($svc->isGroupWideOperator());
        $this->assertSame(HoldingAccessService::ACTOR_COMPANY_OPERATOR, $svc->resolveActorKind());
        $this->assertSame([], $svc->actorCompanyIds());

        $this->expectException(RuntimeException::class);
        $svc->assertCanManageTenantUser('any-tu-id');
    }

    #[Test]
    public function actor_with_company_scopes_is_delegated_company_operator(): void
    {
        Context::add('security_context', ['is_owner' => false, 'tenant_id' => 't1']);
        ScopeContext::getInstance()->setScopes([
            ['scope_id' => 's1', 'scope_type' => 'COMPANY', 'reference_id' => 'c-a'],
            ['scope_id' => 's2', 'scope_type' => 'COMPANY', 'reference_id' => 'c-b'],
        ]);

        $svc = $this->serviceWithCompanyCount(3);

        $this->assertFalse($svc->isTechnicalOwner());
        $this->assertTrue($svc->isCompanyOperator());
        $this->assertSame(HoldingAccessService::ACTOR_COMPANY_OPERATOR, $svc->resolveActorKind());
        $this->assertFalse($svc->isGroupWideActor());
        $this->assertFalse($svc->isIdentityMutateGroupWide());
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

        $svc = $this->serviceWithCompanyCount(1);

        $this->assertFalse($svc->isGroupObserver());
    }

    #[Test]
    public function group_observer_can_list_but_cannot_mutate_members(): void
    {
        Context::add('security_context', ['is_owner' => false, 'tenant_id' => 't1']);
        ScopeContext::getInstance()->setScopes([]);

        $svc = $this->serviceAsGroupObserver(3);

        $this->assertTrue($svc->isGroupObserver());
        $this->assertSame(HoldingAccessService::ACTOR_GROUP_OBSERVER, $svc->resolveActorKind());
        $this->assertTrue($svc->isGroupWideActor());
        $this->assertFalse($svc->isIdentityMutateGroupWide());
        $this->assertFalse($svc->isGroupWideOperator());

        $this->expectException(RuntimeException::class);
        $svc->assertCanManageTenantUser('any-tu-id');
    }
}
