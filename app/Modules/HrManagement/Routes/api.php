<?php

use Illuminate\Support\Facades\Route;
use App\Modules\HrManagement\Controllers\EmployeeController;
use App\Modules\HrManagement\Controllers\AttendanceRecordController;
use App\Modules\HrManagement\Controllers\EmployeeProfileController;
use App\Modules\HrManagement\Controllers\PayrollRecordController;
use App\Modules\HrManagement\Controllers\HrDocumentController;

/*
|--------------------------------------------------------------------------
| HR Management API Routes
| Prefix by ModuleServiceProvider: /api/hr-management
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'tenant.context', 'load.scopes'])->group(function () {

    Route::get('employees', [EmployeeController::class, 'index'])
        ->middleware('permission:hr.employee.view');
    Route::post('employees', [EmployeeController::class, 'store'])
        ->middleware('permission:hr.employee.create');
    Route::get('employees/{id}', [EmployeeController::class, 'show'])
        ->middleware('permission:hr.employee.view');
    Route::put('employees/{id}', [EmployeeController::class, 'update'])
        ->middleware('permission:hr.employee.update');
    Route::post('employees/{id}/terminate', [EmployeeController::class, 'terminate'])
        ->middleware('permission:hr.employee.terminate');

    Route::get('employees/{employeeId}/attendance', [AttendanceRecordController::class, 'index'])
        ->middleware('permission:hr.attendance.view');
    Route::post('employees/{employeeId}/attendance/clock-in', [AttendanceRecordController::class, 'clockIn'])
        ->middleware('permission:hr.attendance.clock');
    Route::post('employees/{employeeId}/attendance/clock-out', [AttendanceRecordController::class, 'clockOut'])
        ->middleware('permission:hr.attendance.clock');

    Route::get('employees/{employeeId}/profile', [EmployeeProfileController::class, 'show'])
        ->middleware('permission:hr.employee.view');
    Route::put('employees/{employeeId}/profile', [EmployeeProfileController::class, 'upsert'])
        ->middleware('permission:hr.employee.update');

    Route::get('employees/{employeeId}/payroll', [PayrollRecordController::class, 'index'])
        ->middleware('permission:hr.payroll.view');
    Route::post('payroll-records', [PayrollRecordController::class, 'store'])
        ->middleware('permission:hr.payroll.create');
    Route::post('payroll-records/{id}/disburse', [PayrollRecordController::class, 'disburse'])
        ->middleware('permission:hr.payroll.disburse');

    Route::get('employees/{employeeId}/documents', [HrDocumentController::class, 'index'])
        ->middleware('permission:hr.document.view');
    Route::post('documents', [HrDocumentController::class, 'store'])
        ->middleware('permission:hr.document.create');

});
