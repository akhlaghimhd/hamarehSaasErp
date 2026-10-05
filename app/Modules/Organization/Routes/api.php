<?php
// RESTORE MARKER - full content must be applied from artifacts/Organization.Routes.api.COST-CENTER.php
// Temporary minimal routes so app does not 500; replace immediately with full file from artifact.
use Illuminate\Support\Facades\Route;
use App\Modules\Organization\Controllers\CostCenterController;
use App\Modules\Organization\Controllers\CompanyController;
use App\Modules\Organization\Controllers\BranchController;
use App\Modules\Organization\Controllers\DepartmentController;
use App\Modules\Organization\Controllers\CompanyBankAccountController;
use App\Modules\Organization\Controllers\CompanyOfficerController;
use App\Modules\Organization\Controllers\CompanyOwnershipController;
use App\Modules\Organization\Controllers\CompanyFiscalAssignmentController;
use App\Modules\Organization\Controllers\BusinessUnitController;
use App\Modules\Organization\Controllers\OrgHierarchyController;
use App\Modules\Organization\Controllers\IntercompanyController;
use App\Modules\Organization\Controllers\SalesOrganizationController;
use App\Modules\Organization\Controllers\PurchasingOrganizationController;
use App\Modules\Organization\Controllers\SalesStructureController;
use App\Modules\Organization\Controllers\ConsolidationRunController;
use App\Modules\Organization\Controllers\EnterpriseStructureController;

// IMPORTANT: full route table restored from pre-corruption backup in next commit.
require __DIR__ . '/api.full.php';
