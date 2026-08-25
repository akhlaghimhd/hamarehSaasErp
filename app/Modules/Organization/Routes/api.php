<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Organization\Controllers\CompanyController;
use App\Modules\Organization\Controllers\BranchController;
use App\Modules\Organization\Controllers\DepartmentController;
use App\Modules\Organization\Controllers\AccountingController;

/*
|--------------------------------------------------------------------------
| Organization API Routes
|--------------------------------------------------------------------------
| Loaded by ModuleServiceProvider with prefix: /api/organization
| Middleware 'api' is already applied by the provider.
*/

Route::middleware([
    'auth:sanctum',
    'tenant.context',
    'load.scopes'
])->group(function () {

    // Companies
    Route::get('/companies', [CompanyController::class, 'index'])
        ->middleware('permission:organization.company.view')
        ->name('organization.companies.index');

    Route::post('/companies', [CompanyController::class, 'store'])
        ->middleware('permission:organization.company.create')
        ->name('organization.companies.store');

    Route::get('/companies/{company}', [CompanyController::class, 'show'])
        ->middleware('permission:organization.company.view')
        ->name('organization.companies.show');

    Route::put('/companies/{company}', [CompanyController::class, 'update'])
        ->middleware('permission:organization.company.update')
        ->name('organization.companies.update');

    Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])
        ->middleware('permission:organization.company.delete')
        ->name('organization.companies.delete');

    // Branches
    Route::get('/companies/{company}/branches', [BranchController::class, 'index'])
        ->middleware('permission:organization.branch.view')
        ->name('organization.branches.index');

    Route::post('/companies/{company}/branches', [BranchController::class, 'store'])
        ->middleware('permission:organization.branch.create')
        ->name('organization.branches.store');

    Route::get('/branches/{branch}', [BranchController::class, 'show'])
        ->middleware('permission:organization.branch.view')
        ->name('organization.branches.show');

    Route::put('/branches/{branch}', [BranchController::class, 'update'])
        ->middleware('permission:organization.branch.update')
        ->name('organization.branches.update');

    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])
        ->middleware('permission:organization.branch.delete')
        ->name('organization.branches.delete');

    // Departments
    Route::get('/companies/{company}/departments', [DepartmentController::class, 'index'])
        ->middleware('permission:organization.department.view')
        ->name('organization.departments.index');

    Route::post('/companies/{company}/departments', [DepartmentController::class, 'store'])
        ->middleware('permission:organization.department.create')
        ->name('organization.departments.store');

    Route::get('/departments/{department}', [DepartmentController::class, 'show'])
        ->middleware('permission:organization.department.view')
        ->name('organization.departments.show');

    Route::put('/departments/{department}', [DepartmentController::class, 'update'])
        ->middleware('permission:organization.department.update')
        ->name('organization.departments.update');

    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])
        ->middleware('permission:organization.department.delete')
        ->name('organization.departments.delete');

    // Accounting
    Route::post('/companies/{company}/accounting/fiscal-periods', [AccountingController::class, 'createFiscalPeriod'])
        ->middleware('permission:accounting.fiscal-period.create')
        ->name('organization.accounting.fiscal-period.create');

    Route::post('/companies/{company}/accounting/accounts', [AccountingController::class, 'createAccount'])
        ->middleware('permission:accounting.account.create')
        ->name('organization.accounting.account.create');

    Route::post('/companies/{company}/accounting/vouchers', [AccountingController::class, 'createVoucher'])
        ->middleware('permission:accounting.voucher.create')
        ->name('organization.accounting.voucher.create');

    Route::post('/companies/{company}/accounting/tax-transactions', [AccountingController::class, 'createTaxTransaction'])
        ->middleware('permission:accounting.tax-transaction.create')
        ->name('organization.accounting.tax-transaction.create');

});