<?php

namespace App\Modules\Organization\Services;

use App\Base\Context\TenantContext;
use App\Modules\SaasPlatform\Services\FeatureCatalogService;

/**
 * DEBT-ORG-003 — thin pack asserts for Sales/Purch structure creates.
 * Call from create entry points (service layer).
 */
final class OrgSalesPurchPackGuard
{
    public static function assertSalesStructure(): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        app(FeatureCatalogService::class)->assertEnabled(
            $tenantId,
            FeatureCatalogService::CODE_ORG_SALES_STRUCTURE
        );
    }

    public static function assertPurchStructure(): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        app(FeatureCatalogService::class)->assertEnabled(
            $tenantId,
            FeatureCatalogService::CODE_ORG_PURCH_STRUCTURE
        );
    }
}
