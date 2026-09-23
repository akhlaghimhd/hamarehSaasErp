<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Organization\Controllers\CompanyController;
use App\Modules\Organization\Controllers\BranchController;
use App\Modules\Organization\Controllers\DepartmentController;

/*
|--------------------------------------------------------------------------
| Organization API Routes
|--------------------------------------------------------------------------
| Loaded by ModuleServiceProvider with prefix: /api/organization
|
| Address/contact CRUD for COMPANY uses MasterData SoT endpoints:
|   POST/GET/PUT/DELETE /api/master-data/entity-addresses
|   POST/GET/PUT/DELETE /api/master-data/entity-contact-points
| with entity_type=COMPANY and entity_id=<company_id> (Law 5.1).
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
        ->middleware([
            'permission:organization.company.view',
            'scope:COMPANY,company',
        ])
        ->name('organization.companies.show');

    Route::put('/companies/{company}', [CompanyController::class, 'update'])
        ->middleware([
            'permission:organization.company.update',
            'scope:COMPANY,company',
        ])
        ->name('organization.companies.update');

    Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])
        ->middleware([
            'permission:organization.company.delete',
            'scope:COMPANY,company',
        ])
        ->name('organization.companies.delete');

    // Branches
    Route::get('/companies/{company}/branches', [BranchController::class, 'index'])
        ->middleware([
            'permission:organization.branch.view',
            'scope:COMPANY,company',
        ])
        ->name('organization.branches.index');

    Route::post('/companies/{company}/branches', [BranchController::class, 'store'])
        ->middleware([
            'permission:organization.branch.create',
            'scope:COMPANY,company',
        ])
        ->name('organization.branches.store');

    Route::get('/branches/{branch}', [BranchController::class, 'show'])
        ->middleware([
            'permission:organization.branch.view',
            'scope:BRANCH,branch',
        ])
        ->name('organization.branches.show');

    Route::put('/branches/{branch}', [BranchController::class, 'update'])
        ->middleware([
            'permission:organization.branch.update',
            'scope:BRANCH,branch',
        ])
        ->name('organization.branches.update');

    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])
        ->middleware([
            'permission:organization.branch.delete',
            'scope:BRANCH,branch',
        ])
        ->name('organization.branches.delete');

    // Departments
    Route::get('/companies/{company}/departments', [DepartmentController::class, 'index'])
        ->middleware([
            'permission:organization.department.view',
            'scope:COMPANY,company',
        ])
        ->name('organization.departments.index');

    Route::post('/companies/{company}/departments', [DepartmentController::class, 'store'])
        ->middleware([
            'permission:organization.department.create',
            'scope:COMPANY,company',
        ])
        ->name('organization.departments.store');

    Route::get('/departments/{department}', [DepartmentController::class, 'show'])
        ->middleware([
            'permission:organization.department.view',
            'scope:DEPARTMENT,department',
        ])
        ->name('organization.departments.show');

    Route::put('/departments/{department}', [DepartmentController::class, 'update'])
        ->middleware([
            'permission:organization.department.update',
            'scope:DEPARTMENT,department',
        ])
        ->name('organization.departments.update');

    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])
        ->middleware([
            'permission:organization.department.delete',
            'scope:DEPARTMENT,department',
        ])
        ->name('organization.departments.delete');

});
