<?php

use Illuminate\Support\Facades\Route;
use App\Modules\ProjectManagement\Controllers\ProjectController;
use App\Modules\ProjectManagement\Controllers\ProjectTaskController;
use App\Modules\ProjectManagement\Controllers\ProjectMemberController;

/*
|--------------------------------------------------------------------------
| Project Management API Routes
| Prefix by ModuleServiceProvider: /api/project-management
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'tenant.context', 'load.scopes'])->group(function () {

    Route::get('projects', [ProjectController::class, 'index'])
        ->middleware('permission:project.view');
    Route::post('projects', [ProjectController::class, 'store'])
        ->middleware('permission:project.create');
    Route::get('projects/{id}', [ProjectController::class, 'show'])
        ->middleware('permission:project.view');
    Route::post('projects/{id}/activate', [ProjectController::class, 'activate'])
        ->middleware('permission:project.update');
    Route::post('projects/{id}/complete', [ProjectController::class, 'complete'])
        ->middleware('permission:project.update');

    Route::get('projects/{projectId}/tasks', [ProjectTaskController::class, 'index'])
        ->middleware('permission:project.task.view');
    Route::post('projects/{projectId}/tasks', [ProjectTaskController::class, 'store'])
        ->middleware('permission:project.task.create');
    Route::post('tasks/{taskId}/start', [ProjectTaskController::class, 'start'])
        ->middleware('permission:project.task.update');
    Route::post('tasks/{taskId}/complete', [ProjectTaskController::class, 'complete'])
        ->middleware('permission:project.task.update');

    Route::get('projects/{projectId}/members', [ProjectMemberController::class, 'index'])
        ->middleware('permission:project.member.view');
    Route::post('projects/{projectId}/members', [ProjectMemberController::class, 'store'])
        ->middleware('permission:project.member.create');
    Route::post('members/{memberId}/remove', [ProjectMemberController::class, 'remove'])
        ->middleware('permission:project.member.update');

});
