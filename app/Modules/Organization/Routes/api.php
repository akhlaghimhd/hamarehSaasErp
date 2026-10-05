<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Organization\Controllers\CompanyController;
use App\Modules\Organization\Controllers\BranchController;
use App\Modules\Organization\Controllers\DepartmentController;
use App\Modules\Organization\Controllers\BusinessUnitController;
use App\Modules\Organization\Controllers\OrgHierarchyController;
use App\Modules\Organization\Controllers\IntercompanyController;
use App\Modules\Organization\Controllers\CompanyBankAccountController;
use App\Modules\Organization\Controllers\CompanyOfficerController;
use App\Modules\Organization\Controllers\CostCenterController;
use App\Modules\Organization\Controllers\CompanyOwnershipController;
use App\Modules\Organization\Controllers\CompanyFiscalAssignmentController;
use App\Modules\Organization\Controllers\SalesOrganizationController;
use App\Modules\Organization\Controllers\PurchasingOrganizationController;
use App\Modules\Organization\Controllers\SalesStructureController;
use App\Modules\Organization\Controllers\ConsolidationRunController;
use App\Modules\Organization\Controllers\EnterpriseStructureController;

Route::middleware([
    'auth:sanctum',
    'tenant.context',
    'load.scopes'
])->group(function () {

    Route::get('/companies', [CompanyController::class, 'index'])
        ->middleware('permission:organization.company.view');
    Route::post('/companies', [CompanyController::class, 'store'])
        ->middleware('permission:organization.company.create');
    Route::get('/companies/{company}', [CompanyController::class, 'show'])
        ->middleware(['permission:organization.company.view', 'scope:COMPANY,company']);
    Route::put('/companies/{company}', [CompanyController::class, 'update'])
        ->middleware(['permission:organization.company.update', 'scope:COMPANY,company']);
    Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])
        ->middleware(['permission:organization.company.delete', 'scope:COMPANY,company']);
    Route::post('/companies/{company}/restore', [CompanyController::class, 'restore'])
        ->middleware('permission:organization.company.update');

    Route::get('/companies/{company}/branches', [BranchController::class, 'index'])
        ->middleware(['permission:organization.branch.view', 'scope:COMPANY,company']);
    Route::post('/companies/{company}/branches', [BranchController::class, 'store'])
        ->middleware(['permission:organization.branch.create', 'scope:COMPANY,company']);

    Route::get('/branches', [BranchController::class, 'index'])
        ->middleware('permission:organization.branch.view');

    Route::get('/departments', [DepartmentController::class, 'index'])
        ->middleware('permission:organization.department.view');

    Route::get('/branches/{branch}', [BranchController::class, 'show'])
        ->middleware(['permission:organization.branch.view', 'scope:BRANCH,branch']);
    Route::put('/branches/{branch}', [BranchController::class, 'update'])
        ->middleware(['permission:organization.branch.update', 'scope:BRANCH,branch']);
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])
        ->middleware(['permission:organization.branch.delete', 'scope:BRANCH,branch']);
    Route::post('/branches/{branch}/restore', [BranchController::class, 'restore'])
        ->middleware('permission:organization.branch.update');

    Route::get('/companies/{company}/departments', [DepartmentController::class, 'index'])
        ->middleware(['permission:organization.department.view', 'scope:COMPANY,company']);
    Route::post('/companies/{company}/departments', [DepartmentController::class, 'store'])
        ->middleware(['permission:organization.department.create', 'scope:COMPANY,company']);
    Route::get('/departments/{department}', [DepartmentController::class, 'show'])
        ->middleware(['permission:organization.department.view', 'scope:DEPARTMENT,department']);
    Route::put('/departments/{department}', [DepartmentController::class, 'update'])
        ->middleware(['permission:organization.department.update', 'scope:DEPARTMENT,department']);
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])
        ->middleware(['permission:organization.department.delete', 'scope:DEPARTMENT,department']);
    Route::post('/departments/{department}/restore', [DepartmentController::class, 'restore'])
        ->middleware('permission:organization.department.update');

    Route::get('/companies/{company}/bank-accounts', [CompanyBankAccountController::class, 'index'])
        ->middleware(['permission:organization.bank.view', 'scope:COMPANY,company']);
    Route::post('/companies/{company}/bank-accounts', [CompanyBankAccountController::class, 'store'])
        ->middleware(['permission:organization.bank.manage', 'scope:COMPANY,company']);
    Route::delete('/bank-accounts/{bankAccount}', [CompanyBankAccountController::class, 'destroy'])
        ->middleware('permission:organization.bank.manage');

    Route::get('/companies/{company}/officers', [CompanyOfficerController::class, 'index'])
        ->middleware(['permission:organization.officer.view', 'scope:COMPANY,company']);
    Route::post('/companies/{company}/officers', [CompanyOfficerController::class, 'store'])
        ->middleware(['permission:organization.officer.manage', 'scope:COMPANY,company']);
    Route::delete('/officers/{officer}', [CompanyOfficerController::class, 'destroy'])
        ->middleware('permission:organization.officer.manage');

    Route::get('/companies/{company}/cost-centers', [CostCenterController::class, 'index'])
        ->middleware(['permission:organization.cost_center.view', 'scope:COMPANY,company']);
    Route::post('/companies/{company}/cost-centers', [CostCenterController::class, 'store'])
        ->middleware(['permission:organization.cost_center.manage', 'scope:COMPANY,company']);

    Route::get('/companies/{company}/ownerships', [CompanyOwnershipController::class, 'index'])
        ->middleware(['permission:organization.ownership.view', 'scope:COMPANY,company']);
    Route::post('/companies/{company}/ownerships', [CompanyOwnershipController::class, 'store'])
        ->middleware(['permission:organization.ownership.manage', 'scope:COMPANY,company']);
    Route::put('/ownerships/{ownership}', [CompanyOwnershipController::class, 'update'])
        ->middleware('permission:organization.ownership.manage');
    Route::delete('/ownerships/{ownership}', [CompanyOwnershipController::class, 'destroy'])
        ->middleware('permission:organization.ownership.manage');

    Route::get('/companies/{company}/fiscal-assignments', [CompanyFiscalAssignmentController::class, 'index'])
        ->middleware(['permission:organization.fiscal.view', 'scope:COMPANY,company']);
    Route::post('/companies/{company}/fiscal-assignments', [CompanyFiscalAssignmentController::class, 'store'])
        ->middleware(['permission:organization.fiscal.manage', 'scope:COMPANY,company']);
    Route::delete('/fiscal-assignments/{assignment}', [CompanyFiscalAssignmentController::class, 'destroy'])
        ->middleware('permission:organization.fiscal.manage');

    Route::get('/business-units', [BusinessUnitController::class, 'index'])
        ->middleware('permission:organization.business_unit.view');
    Route::post('/business-units', [BusinessUnitController::class, 'store'])
        ->middleware('permission:organization.business_unit.manage');
    Route::get('/business-units/{businessUnit}', [BusinessUnitController::class, 'show'])
        ->middleware('permission:organization.business_unit.view');
    Route::put('/business-units/{businessUnit}', [BusinessUnitController::class, 'update'])
        ->middleware('permission:organization.business_unit.manage');
    Route::delete('/business-units/{businessUnit}', [BusinessUnitController::class, 'destroy'])
        ->middleware('permission:organization.business_unit.manage');
    Route::post('/business-units/{businessUnit}/restore', [BusinessUnitController::class, 'restore'])
        ->middleware('permission:organization.business_unit.manage');
    Route::post('/business-units/{businessUnit}/companies', [BusinessUnitController::class, 'assignCompany'])
        ->middleware('permission:organization.business_unit.manage');
    Route::put('/business-units/{businessUnit}/companies', [BusinessUnitController::class, 'syncCompanies'])
        ->middleware('permission:organization.business_unit.manage');
    Route::delete('/business-units/{businessUnit}/companies/{company}', [BusinessUnitController::class, 'unassignCompany'])
        ->middleware('permission:organization.business_unit.manage');

    Route::get('/hierarchy-purposes', [OrgHierarchyController::class, 'purposes'])
        ->middleware('permission:organization.hierarchy.view');
    Route::get('/hierarchy-report-contracts', [OrgHierarchyController::class, 'reportContracts'])
        ->middleware('permission:organization.hierarchy.view');

    Route::get('/hierarchies/health', [OrgHierarchyController::class, 'health'])
        ->middleware('permission:organization.hierarchy.view');
    Route::get('/hierarchies/rebuild/preview', [OrgHierarchyController::class, 'previewRebuild'])
        ->middleware('permission:organization.hierarchy.view');
    Route::post('/hierarchies/rebuild', [OrgHierarchyController::class, 'rebuild'])
        ->middleware('permission:organization.hierarchy.manage');
    Route::post('/hierarchies/nodes/bulk', [OrgHierarchyController::class, 'bulkNodes'])
        ->middleware('permission:organization.hierarchy.manage');
    Route::delete('/hierarchies/nodes/{node}', [OrgHierarchyController::class, 'destroyNode'])
        ->middleware('permission:organization.hierarchy.manage');
    Route::post('/hierarchies/nodes/{node}/restore', [OrgHierarchyController::class, 'restoreNode'])
        ->middleware('permission:organization.hierarchy.manage');
    Route::patch('/hierarchies/nodes/{node}/active', [OrgHierarchyController::class, 'setNodeActive'])
        ->middleware('permission:organization.hierarchy.manage');
    Route::patch('/hierarchies/nodes/{node}', [OrgHierarchyController::class, 'updateNode'])
        ->middleware('permission:organization.hierarchy.manage');

    Route::get('/hierarchies', [OrgHierarchyController::class, 'index'])
        ->middleware('permission:organization.hierarchy.view');
    Route::post('/hierarchies', [OrgHierarchyController::class, 'store'])
        ->middleware('permission:organization.hierarchy.manage');
    Route::put('/hierarchies/{hierarchy}', [OrgHierarchyController::class, 'update'])
        ->middleware('permission:organization.hierarchy.manage');
    Route::delete('/hierarchies/{hierarchy}', [OrgHierarchyController::class, 'destroy'])
        ->middleware('permission:organization.hierarchy.manage');
    Route::post('/hierarchies/{hierarchy}/restore', [OrgHierarchyController::class, 'restore'])
        ->middleware('permission:organization.hierarchy.manage');
    Route::patch('/hierarchies/{hierarchy}/active', [OrgHierarchyController::class, 'setActive'])
        ->middleware('permission:organization.hierarchy.manage');
    Route::get('/hierarchies/{hierarchy}/nodes', [OrgHierarchyController::class, 'nodes'])
        ->middleware('permission:organization.hierarchy.view');
    Route::post('/hierarchies/{hierarchy}/nodes', [OrgHierarchyController::class, 'addNode'])
        ->middleware('permission:organization.hierarchy.manage');
    Route::post('/hierarchies/{hierarchy}/nodes/reorder', [OrgHierarchyController::class, 'reorderNodes'])
        ->middleware('permission:organization.hierarchy.manage');

    Route::get('/intercompany/document-types', [IntercompanyController::class, 'documentTypes'])
        ->middleware('permission:organization.intercompany.view');
    Route::get('/intercompany/partners', [IntercompanyController::class, 'partners'])
        ->middleware('permission:organization.intercompany.view');
    Route::post('/intercompany/partners', [IntercompanyController::class, 'storePartner'])
        ->middleware('permission:organization.intercompany.manage');
    Route::put('/intercompany/partners/{partner}', [IntercompanyController::class, 'updatePartner'])
        ->middleware('permission:organization.intercompany.manage');
    Route::delete('/intercompany/partners/{partner}', [IntercompanyController::class, 'destroyPartner'])
        ->middleware('permission:organization.intercompany.manage');
    Route::get('/intercompany/rules', [IntercompanyController::class, 'rules'])
        ->middleware('permission:organization.intercompany.view');
    Route::post('/intercompany/rules', [IntercompanyController::class, 'storeRule'])
        ->middleware('permission:organization.intercompany.manage');
    Route::put('/intercompany/rules/{rule}', [IntercompanyController::class, 'updateRule'])
        ->middleware('permission:organization.intercompany.manage');
    Route::delete('/intercompany/rules/{rule}', [IntercompanyController::class, 'destroyRule'])
        ->middleware('permission:organization.intercompany.manage');

    Route::get('/sales-organizations', [SalesOrganizationController::class, 'index'])
        ->middleware('permission:organization.sales_org.view');
    Route::post('/sales-organizations', [SalesOrganizationController::class, 'store'])
        ->middleware('permission:organization.sales_org.manage');
    Route::get('/sales-organizations/{salesOrg}', [SalesOrganizationController::class, 'show'])
        ->middleware('permission:organization.sales_org.view');
    Route::put('/sales-organizations/{salesOrg}', [SalesOrganizationController::class, 'update'])
        ->middleware('permission:organization.sales_org.manage');
    Route::delete('/sales-organizations/{salesOrg}', [SalesOrganizationController::class, 'destroy'])
        ->middleware('permission:organization.sales_org.manage');
    Route::post('/sales-organizations/{salesOrg}/restore', [SalesOrganizationController::class, 'restore'])
        ->middleware('permission:organization.sales_org.manage');
    Route::get('/sales-organizations/{salesOrg}/assignments', [SalesOrganizationController::class, 'assignments'])
        ->middleware('permission:organization.sales_org.view');
    Route::post('/sales-organizations/{salesOrg}/assignments', [SalesOrganizationController::class, 'assign'])
        ->middleware('permission:organization.sales_org.manage');
    Route::delete('/sales-org-assignments/{assignment}', [SalesOrganizationController::class, 'unassign'])
        ->middleware('permission:organization.sales_org.manage');

    Route::get('/purchasing-organizations', [PurchasingOrganizationController::class, 'index'])
        ->middleware('permission:organization.purch_org.view');
    Route::post('/purchasing-organizations', [PurchasingOrganizationController::class, 'store'])
        ->middleware('permission:organization.purch_org.manage');
    Route::get('/purchasing-organizations/{purchOrg}', [PurchasingOrganizationController::class, 'show'])
        ->middleware('permission:organization.purch_org.view');
    Route::put('/purchasing-organizations/{purchOrg}', [PurchasingOrganizationController::class, 'update'])
        ->middleware('permission:organization.purch_org.manage');
    Route::delete('/purchasing-organizations/{purchOrg}', [PurchasingOrganizationController::class, 'destroy'])
        ->middleware('permission:organization.purch_org.manage');
    Route::post('/purchasing-organizations/{purchOrg}/restore', [PurchasingOrganizationController::class, 'restore'])
        ->middleware('permission:organization.purch_org.manage');
    Route::get('/purchasing-organizations/{purchOrg}/assignments', [PurchasingOrganizationController::class, 'assignments'])
        ->middleware('permission:organization.purch_org.view');
    Route::post('/purchasing-organizations/{purchOrg}/assignments', [PurchasingOrganizationController::class, 'assign'])
        ->middleware('permission:organization.purch_org.manage');
    Route::delete('/purch-org-assignments/{assignment}', [PurchasingOrganizationController::class, 'unassign'])
        ->middleware('permission:organization.purch_org.manage');

    Route::get('/distribution-channels', [SalesStructureController::class, 'channels'])
        ->middleware('permission:organization.sales_structure.view');
    Route::post('/distribution-channels', [SalesStructureController::class, 'storeChannel'])
        ->middleware('permission:organization.sales_structure.manage');
    Route::put('/distribution-channels/{channel}', [SalesStructureController::class, 'updateChannel'])
        ->middleware('permission:organization.sales_structure.manage');
    Route::delete('/distribution-channels/{channel}', [SalesStructureController::class, 'destroyChannel'])
        ->middleware('permission:organization.sales_structure.manage');
    Route::post('/distribution-channels/{channel}/restore', [SalesStructureController::class, 'restoreChannel'])
        ->middleware('permission:organization.sales_structure.manage');

    Route::get('/product-divisions', [SalesStructureController::class, 'divisions'])
        ->middleware('permission:organization.sales_structure.view');
    Route::post('/product-divisions', [SalesStructureController::class, 'storeDivision'])
        ->middleware('permission:organization.sales_structure.manage');
    Route::put('/product-divisions/{division}', [SalesStructureController::class, 'updateDivision'])
        ->middleware('permission:organization.sales_structure.manage');
    Route::delete('/product-divisions/{division}', [SalesStructureController::class, 'destroyDivision'])
        ->middleware('permission:organization.sales_structure.manage');
    Route::post('/product-divisions/{division}/restore', [SalesStructureController::class, 'restoreDivision'])
        ->middleware('permission:organization.sales_structure.manage');

    Route::get('/sales-areas', [SalesStructureController::class, 'salesAreas'])
        ->middleware('permission:organization.sales_structure.view');
    Route::post('/sales-areas', [SalesStructureController::class, 'storeSalesArea'])
        ->middleware('permission:organization.sales_structure.manage');
    Route::put('/sales-areas/{salesArea}', [SalesStructureController::class, 'updateSalesArea'])
        ->middleware('permission:organization.sales_structure.manage');
    Route::delete('/sales-areas/{salesArea}', [SalesStructureController::class, 'destroySalesArea'])
        ->middleware('permission:organization.sales_structure.manage');
    Route::post('/sales-areas/{salesArea}/restore', [SalesStructureController::class, 'restoreSalesArea'])
        ->middleware('permission:organization.sales_structure.manage');

    Route::get('/sales-offices', [SalesStructureController::class, 'offices'])
        ->middleware('permission:organization.sales_structure.view');
    Route::post('/sales-offices', [SalesStructureController::class, 'storeOffice'])
        ->middleware('permission:organization.sales_structure.manage');
    Route::put('/sales-offices/{office}', [SalesStructureController::class, 'updateOffice'])
        ->middleware('permission:organization.sales_structure.manage');
    Route::delete('/sales-offices/{office}', [SalesStructureController::class, 'destroyOffice'])
        ->middleware('permission:organization.sales_structure.manage');
    Route::post('/sales-offices/{office}/restore', [SalesStructureController::class, 'restoreOffice'])
        ->middleware('permission:organization.sales_structure.manage');
    Route::get('/sales-offices/{office}/groups', [SalesStructureController::class, 'groups'])
        ->middleware('permission:organization.sales_structure.view');
    Route::post('/sales-offices/{office}/groups', [SalesStructureController::class, 'storeGroup'])
        ->middleware('permission:organization.sales_structure.manage');
    Route::put('/sales-groups/{group}', [SalesStructureController::class, 'updateGroup'])
        ->middleware('permission:organization.sales_structure.manage');
    Route::delete('/sales-groups/{group}', [SalesStructureController::class, 'destroyGroup'])
        ->middleware('permission:organization.sales_structure.manage');
    Route::post('/sales-groups/{group}/restore', [SalesStructureController::class, 'restoreGroup'])
        ->middleware('permission:organization.sales_structure.manage');

    Route::get('/consolidation-runs', [ConsolidationRunController::class, 'index'])
        ->middleware('permission:organization.consolidation.view');
    Route::post('/consolidation-runs', [ConsolidationRunController::class, 'store'])
        ->middleware('permission:organization.consolidation.manage');
    Route::post('/consolidation-runs/{run}/snapshot', [ConsolidationRunController::class, 'snapshot'])
        ->middleware('permission:organization.consolidation.manage');

    Route::get('/enterprise-structure', [EnterpriseStructureController::class, 'show'])
        ->middleware('permission:organization.company.view');
    Route::post('/structure/apply-template', [EnterpriseStructureController::class, 'applyTemplate'])
        ->middleware('permission:organization.company.manage');
});
