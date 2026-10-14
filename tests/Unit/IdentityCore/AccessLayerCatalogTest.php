<?php

namespace Tests\Unit\IdentityCore;

use App\Modules\IdentityCore\Catalog\AccessLayerCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessLayerCatalogTest extends TestCase
{
    #[Test]
    public function platform_admin_pack_excludes_voucher_post(): void
    {
        $codes = AccessLayerCatalog::platformAdminPermissionCodes();

        $this->assertContains('identity.role.create', $codes);
        $this->assertContains(AccessLayerCatalog::PERM_GROUP_OBSERVE, $codes);
        $this->assertNotContains('accounting.voucher.post', $codes);
    }

    #[Test]
    public function group_read_pack_is_view_only(): void
    {
        $codes = AccessLayerCatalog::groupReadPermissionCodes();

        $this->assertContains(AccessLayerCatalog::PERM_GROUP_OBSERVE, $codes);
        $this->assertContains('accounting.voucher.view', $codes);
        $this->assertNotContains('accounting.voucher.post', $codes);
        $this->assertNotContains('identity.role.create', $codes);
    }

    #[Test]
    public function layer_for_permission_code_maps_observe_to_group_read(): void
    {
        $this->assertSame(
            AccessLayerCatalog::LAYER_GROUP_READ,
            AccessLayerCatalog::layerForPermissionCode('identity.group.observe')
        );
        $this->assertSame(
            AccessLayerCatalog::LAYER_PLATFORM_ADMIN,
            AccessLayerCatalog::layerForPermissionCode('identity.role.create')
        );
    }

    #[Test]
    public function role_template_codes_include_holding_templates(): void
    {
        $codes = AccessLayerCatalog::roleTemplateCodes();

        $this->assertContains(AccessLayerCatalog::ROLE_GROUP_OBSERVER, $codes);
        $this->assertContains(AccessLayerCatalog::ROLE_COMPANY_OPERATOR, $codes);
        $this->assertContains(AccessLayerCatalog::ROLE_COMPANY_USER, $codes);
        $this->assertContains(AccessLayerCatalog::ROLE_PLATFORM_ADMIN, $codes);
    }
}
