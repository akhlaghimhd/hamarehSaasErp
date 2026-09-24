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

    Route::get('/companies/{company}/branches', [BranchController::class, 'index'])
        ->middleware(['permission:organization.branch.view', 'scope:COMPANY,company']);
    Route::post('/companies/{company}/branches', [BranchController::class, 'store'])
        ->middleware(['permission:organization.branch.create', 'scope:COMPANY,company']);
    Route::get('/branches/{branch}', [BranchController::class, 'show'])
        ->middleware(['permission:organization.branch.view', 'scope:BRANCH,branch']);
    Route::put('/branches/{branch}', [BranchController::class, 'update'])
        ->middleware(['permission:organization.branch.update', 'scope:BRANCH,branch']);
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])
        ->middleware(['permission:organization.branch.delete', 'scope:BRANCH,branch']);

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
    Route::post('/business-units/{businessUnit}/companies', [BusinessUnitController::class, 'assignCompany'])
        ->middleware('permission:organization.business_unit.manage');

    Route::get('/hierarchies', [OrgHierarchyController::class, 'index'])
        ->middleware('permission:organization.hierarchy.view');
    Route::post('/hierarchies', [OrgHierarchyController::class, 'store'])
        ->middleware('permission:organization.hierarchy.manage');
    Route::get('/hierarchies/{hierarchy}/nodes', [OrgHierarchyController::class, 'nodes'])
        ->middleware('permission:organization.hierarchy.view');
    Route::post('/hierarchies/{hierarchy}/nodes', [OrgHierarchyController::class, 'addNode'])
        ->middleware('permission:organization.hierarchy.manage');

    Route::get('/intercompany/partners', [IntercompanyController::class, 'partners'])
        ->middleware('permission:organization.intercompany.view');
    Route::post('/intercompany/partners', [IntercompanyController::class, 'storePartner'])
        ->middleware('permission:organization.intercompany.manage');
    Route::get('/intercompany/rules', [IntercompanyController::class, 'rules'])
        ->middleware('permission:organization.intercompany.view');
    Route::post('/intercompany/rules', [IntercompanyController::class, 'storeRule'])
        ->middleware('permission:organization.intercompany.manage');

    Route::get('/sales-organizations', [SalesOrganizationController::class, 'index'])
        ->middleware('permission:organization.sales_org.view');
    Route::post('/sales-organizations', [SalesOrganizationController::class, 'store'])
        ->middleware('permission:organization.sales_org.manage');
    Route::post('/sales-organizations/{salesOrg}/assignments', [SalesOrganizationController::class, 'assign'])
        ->middleware('permission:organization.sales_org.manage');

    Route::get('/purchasing-organizations', [PurchasingOrganizationController::class, 'index'])
        ->middleware('permission:organization.purch_org.view');
    Route::post('/purchasing-organizations', [PurchasingOrganizationController::class, 'store'])
        ->middleware('permission:organization.purch_org.manage');
    Route::post('/purchasing-organizations/{purchOrg}/assignments', [PurchasingOrganizationController::class, 'assign'])
        ->middleware('permission:organization.purch_org.manage');

    Route::get('/consolidation-runs', [ConsolidationRunController::class, 'index'])
        ->middleware('permission:organization.consolidation.view');
    Route::post('/consolidation-runs', [ConsolidationRunController::class, 'store'])
        ->middleware('permission:organization.consolidation.manage');
    Route::post('/consolidation-runs/{consolRun}/snapshot', [ConsolidationRunController::class, 'snapshot'])
        ->middleware('permission:organization.consolidation.manage');

    Route::post('/structure/apply-template', [EnterpriseStructureController::class, 'applyTemplate'])
        ->middleware('permission:organization.structure.configure');
});
