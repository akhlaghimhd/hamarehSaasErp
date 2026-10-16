<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\IdentityCore;

use App\Modules\IdentityCore\Catalog\AccessLayerCatalog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * FIN-HOLD-W5 / ADR-FIN-003 L12 — role seed audit: group read must not include finance operate.
 */
class AccessLayerCatalogTest extends TestCase
{
    #[Test]
    public function group_read_pack_contains_finance_view_and_no_operate(): void
    {
        $read = AccessLayerCatalog::groupReadPermissionCodes();

        $this->assertContains(AccessLayerCatalog::PERM_GROUP_OBSERVE, $read);
        $this->assertContains('finance.journal.view', $read);
        $this->assertContains('finance.report.view', $read);
        $this->assertContains('finance.coa.view', $read);

        foreach (AccessLayerCatalog::financeOperatePermissionCodes() as $code) {
            $this->assertNotContains(
                $code,
                $read,
                "group observer must not receive operate permission: {$code}"
            );
        }
    }

    #[Test]
    public function company_operate_pack_includes_finance_mutate(): void
    {
        $op = AccessLayerCatalog::companyOperatePermissionCodes();

        $this->assertContains('finance.journal.create', $op);
        $this->assertContains('finance.journal.post', $op);
        $this->assertContains('finance.coa.create', $op);
        $this->assertContains('finance.journal.view', $op);
    }

    #[Test]
    public function layer_map_marks_finance_operate_as_company_operate(): void
    {
        $this->assertSame(
            AccessLayerCatalog::LAYER_COMPANY_OPERATE,
            AccessLayerCatalog::layerForPermissionCode('finance.journal.post')
        );
        $this->assertSame(
            AccessLayerCatalog::LAYER_GROUP_READ,
            AccessLayerCatalog::layerForPermissionCode('finance.report.view')
        );
        $this->assertSame(
            AccessLayerCatalog::LAYER_GROUP_READ,
            AccessLayerCatalog::layerForPermissionCode(AccessLayerCatalog::PERM_GROUP_OBSERVE)
        );
    }

    #[Test]
    public function platform_admin_pack_does_not_include_finance_operate_all(): void
    {
        $pack = AccessLayerCatalog::platformAdminPermissionCodes();

        foreach (['finance.journal.post', 'finance.journal.create', 'finance.coa.create'] as $code) {
            $this->assertNotContains(
                $code,
                $pack,
                'platform-admin must not auto-include FIN operate (FIN-HOLD-L06)'
            );
        }
    }
}
