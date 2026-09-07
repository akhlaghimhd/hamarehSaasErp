<?php

use Illuminate\Support\Facades\Route;
use App\Modules\HrManagement\Controllers\EmployeeController;
use App\Modules\HrManagement\Controllers\AttendanceRecordController;

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

});
