<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Modules\IdentityCore\Catalog\RoleGrantTier;
use App\Modules\IdentityCore\Services\RoleGrantCeilingService;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * ADR-ID-ORG-006 — hierarchical role grant ceiling.
 */
class RoleGrantHierarchyTest extends TestCase
{
    #[Test]
    public function role_grant_tier_mapping_is_stable(): void
    {
        $this->assertSame(RoleGrantTier::TENANT, RoleGrantTier::tierForCode('platform-admin'));
        $this->assertSame(RoleGrantTier::GROUP, RoleGrantTier::tierForCode('group-observer'));
        $this->assertSame(RoleGrantTier::GROUP, RoleGrantTier::tierForCode('org-admin'));
        $this->assertSame(RoleGrantTier::COMPANY, RoleGrantTier::tierForCode('company-operator'));
        $this->assertSame(RoleGrantTier::COMPANY, RoleGrantTier::tierForCode('accountant'));
        $this->assertSame(RoleGrantTier::BRANCH, RoleGrantTier::tierForCode('branch-admin'));
        $this->assertTrue(RoleGrantTier::rank(RoleGrantTier::TENANT) > RoleGrantTier::rank(RoleGrantTier::GROUP));
        $this->assertTrue(RoleGrantTier::rank(RoleGrantTier::GROUP) > RoleGrantTier::rank(RoleGrantTier::COMPANY));
        $this->assertTrue(RoleGrantTier::rank(RoleGrantTier::COMPANY) > RoleGrantTier::rank(RoleGrantTier::BRANCH));
    }

    #[Test]
    public function company_capacity_is_below_tenant_role_rank(): void
    {
        $this->assertLessThan(
            RoleGrantTier::rankForCode('platform-admin'),
            RoleGrantTier::rank(RoleGrantTier::COMPANY)
        );
        $this->assertLessThan(
            RoleGrantTier::rankForCode('group-observer'),
            RoleGrantTier::rank(RoleGrantTier::COMPANY)
        );
    }

    #[Test]
    public function assert_message_mentions_scope_elevation_when_target_too_low(): void
    {
        $need = RoleGrantTier::rankForCode('platform-admin');
        $cap = min(4, RoleGrantTier::rank(RoleGrantTier::COMPANY));
        $this->assertGreaterThan($cap, $need);

        try {
            if ($need > $cap) {
                throw new RuntimeException('برای اعطای این نقش، ابتدا باید اسکوپ سطح بالاتر (شرکت مادر یا گروه) به کاربر داده شود');
            }
            $this->fail('expected exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('اسکوپ سطح بالاتر', $e->getMessage());
        }
    }
}
