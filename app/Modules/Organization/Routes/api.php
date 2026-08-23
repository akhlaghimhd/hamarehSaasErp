<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Organization\Controllers\CompanyController;
use App\Modules\Organization\Controllers\BranchController;
use App\Modules\Organization\Controllers\DepartmentController;

Route::prefix('organization')
    ->middleware(['api', 'auth:sanctum', 'tenant.context', 'load.scopes'])
    ->group(function () {

        // Companies
        Route::get('companies', [CompanyController::class, 'index'])
            ->middleware('permission:organization.company.view');
        Route::post('companies', [CompanyController::class, 'store'])
            ->middleware('permission:organization.company.create');
        Route::put('companies/{id}', [CompanyController::class, 'update'])
            ->middleware('permission:organization.company.update');
        Route::delete('companies/{id}', [CompanyController::class, 'destroy'])
            ->middleware('permission:organization.company.delete');

        // Branches
        Route::get('branches', [BranchController::class, 'index'])
            ->middleware('permission:organization.branch.view');
        Route::post('branches', [BranchController::class, 'store'])
            ->middleware('permission:organization.branch.create');
        Route::put('branches/{id}', [BranchController::class, 'update'])
            ->middleware('permission:organization.branch.update');
        Route::delete('branches/{id}', [BranchController::class, 'destroy'])
            ->middleware('permission:organization.branch.delete');

        // Departments
        Route::get('departments', [DepartmentController::class, 'index'])
            ->middleware('permission:organization.department.view');
        Route::post('departments', [DepartmentController::class, 'store'])
            ->middleware('permission:organization.department.create');
        Route::put('departments/{id}', [DepartmentController::class, 'update'])
            ->middleware('permission:organization.department.update');
        Route::delete('departments/{id}', [DepartmentController::class, 'destroy'])
            ->middleware('permission:organization.department.delete');
    });