<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Organization\Controllers\CompanyController;
use App\Modules\Organization\Controllers\BranchController;
use App\Modules\Organization\Controllers\DepartmentController;

Route::prefix('organization')->middleware(['api', 'auth:sanctum', 'tenant.context'])->group(function () {
    
    // Companies
    Route::get('companies', [CompanyController::class, 'index']);
    Route::post('companies', [CompanyController::class, 'store']);
    
    // Branches
    Route::get('branches', [BranchController::class, 'index']);
    Route::post('branches', [BranchController::class, 'store']);

    // Departments
    Route::get('departments', [DepartmentController::class, 'index']);
    Route::post('departments', [DepartmentController::class, 'store']);
    Route::put('companies/{id}', [CompanyController::class, 'update']);
    Route::delete('companies/{id}', [CompanyController::class, 'destroy']);
    // Branches (به بخش موجود اضافه شود)
    Route::put('branches/{id}', [BranchController::class, 'update']);
    Route::delete('branches/{id}', [BranchController::class, 'destroy']);

    // Departments (به بخش موجود اضافه شود)
    Route::put('departments/{id}', [DepartmentController::class, 'update']);
    Route::delete('departments/{id}', [DepartmentController::class, 'destroy']);
    
    
});