<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\OrgHierarchy;
use App\Modules\Organization\DTOs\CreateCompanyDTO;
use App\Base\Context\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * ORG-P6-06 — API-driven enterprise structure template apply.
 * Minimal template: ensure primary HQ + optional LEGAL hierarchy root node.
 */
class EnterpriseStructureConfigurator
{
    public function __construct(
        protected CompanyService $companyService = new CompanyService(),
        protected OrgHierarchyService $hierarchyService = new OrgHierarchyService(),
        protected OrganizationEventPublisher $events = new OrganizationEventPublisher()
    ) {
    }

    /**
     * @param  array{hq_name?: string, hq_code?: string, create_legal_hierarchy?: bool}  $template
     */
    public function applyTemplate(array $template = []): array
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        return DB::transaction(function () use ($tenantId, $template) {
            $hqName = $template['hq_name'] ?? 'شرکت اصلی';
            $hqCode = $template['hq_code'] ?? 'HQ';

            $primary = $this->companyService->ensurePrimaryCompanyForTenant($tenantId, $hqName, $hqCode);

            $result = [
                'primary_company_id' => $primary->company_id,
                'hierarchy_id'       => null,
            ];

            if (!empty($template['create_legal_hierarchy'])) {
                $existing = OrgHierarchy::where('tenant_id', $tenantId)
                    ->where('purpose', OrgHierarchy::PURPOSE_LEGAL)
                    ->where('code', 'LEGAL-DEFAULT')
                    ->first();

                if (!$existing) {
                    $hier = $this->hierarchyService->createHierarchy(
                        'LEGAL-DEFAULT',
                        'Legal default',
                        OrgHierarchy::PURPOSE_LEGAL
                    );
                    $this->hierarchyService->addNode($hier->hierarchy_id, 'COMPANY', $primary->company_id);
                    $result['hierarchy_id'] = $hier->hierarchy_id;
                } else {
                    $result['hierarchy_id'] = $existing->hierarchy_id;
                }
            }

            $this->events->publish(
                'structure_template_applied',
                'tenant',
                $tenantId,
                $result
            );

            return $result;
        });
    }
}
