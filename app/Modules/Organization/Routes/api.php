<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Organization\Controllers\CompanyController;
use App\Modules\Organization\Controllers\BranchController;
use App\Modules\Organization\Controllers\DepartmentController;
use App\Modules\Organization\Controllers\EntityAddressController;
use App\Modules\Organization\Controllers\EntityContactPointController;

Route::middleware([
    'auth:sanctum',
    'tenant.context',
    'load.scopes'
])->group(function () {

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

    Route::get('/entities/{entityType}/{entityId}/addresses', [EntityAddressController::class, 'index'])
        ->middleware('permission:organization.company.view')
        ->name('organization.entity-addresses.index');

    Route::post('/entity-addresses', [EntityAddressController::class, 'store'])
        ->middleware('permission:organization.company.update')
        ->name('organization.entity-addresses.store');

    Route::delete('/entity-addresses/{entityAddressId}', [EntityAddressController::class, 'destroy'])
        ->middleware('permission:organization.company.update')
        ->name('organization.entity-addresses.destroy');

    Route::get('/entities/{entityType}/{entityId}/contact-points', [EntityContactPointController::class, 'index'])
        ->middleware('permission:organization.company.view')
        ->name('organization.entity-contact-points.index');

    Route::post('/entity-contact-points', [EntityContactPointController::class, 'store'])
        ->middleware('permission:organization.company.update')
        ->name('organization.entity-contact-points.store');

    Route::delete('/entity-contact-points/{entityContactPointId}', [EntityContactPointController::class, 'destroy'])
        ->middleware('permission:organization.company.update')
        ->name('organization.entity-contact-points.destroy');

});
