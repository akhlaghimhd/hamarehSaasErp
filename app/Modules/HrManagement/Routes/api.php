<?php

use Illuminate\Support\Facades\Route;
use App\Modules\HrManagement\Controllers\EmployeeController;
use App\Modules\HrManagement\Controllers\EmployeeProfileController;
use App\Modules\HrManagement\Controllers\HrDocumentController;
use App\Modules\HrManagement\Controllers\AttendanceRecordController;
use App\Modules\HrManagement\Controllers\PayrollRecordController;

/*
|--------------------------------------------------------------------------
| HR Management API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['api', 'tenant.context'])
    ->prefix('api/hr-management')
    ->group(function () {

        // Employee Routes
        Route::post('/employees', [EmployeeController::class, 'store']);

        // Employee Profile Routes
        Route::post('/employee-profiles', [EmployeeProfileController::class, 'store']);

        // HR Document Routes
        Route::post('/documents', [HrDocumentController::class, 'store']);

        // Attendance Routes
        Route::post('/attendance', [AttendanceRecordController::class, 'store']);

        // Payroll Routes (محاسبات حقوق و دستمزد)
        Route::post('/payroll-records', [PayrollRecordController::class, 'store']);

    });